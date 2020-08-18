<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\Video;
use App\Form\UserType;
use App\Repository\VideoRepository;
use App\Utils\CategoryTreeFrontPage;
use App\Utils\VideoForNoValidSubscription;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Controller\Traits\SaveSubscription;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class FrontController extends AbstractController
{
    use SaveSubscription; 
    /**
     * @Route("/", name="main_page")
     */
    public function index()
    {
        return $this->render('front/index.html.twig');
    }

     /**
     * @Route("/video-list/category/{categoryname},{id}/{page}",defaults={"page":1 }, name="video_list")
     */
     public function videoList($id, $page, CategoryTreeFrontPage $categories, Request $request, VideoForNoValidSubscription $video_no_members) // c_88
    {
        $ids = $categories->getChildIds($id);
        array_push($ids, $id);

        $videos = $this->getDoctrine()
        ->getRepository(Video::class)
        ->findByChildIds($ids ,$page, $request->get('sortby'));

        $categories->getCategoryListAndParent($id);
        return $this->render('front/video_list.html.twig',[
            'subcategories' => $categories,
            'videos'=>$videos,
            'video_no_members' => $video_no_members->check()
        ]);
    }

    /**
     * @Route("/video-details/{video}", name="video_details")
     */
    public function videoDetails(VideoRepository $repo, $video, VideoForNoValidSubscription $video_no_members)
    {
        return $this->render('front/video_details.html.twig',
        [
            'video'=>$repo->videoDetails($video),
            'video_no_members' => $video_no_members->check()
        ]);
    }

    /**
     * @Route("/search-results/{page}",defaults={"page":1 }, methods={"GET"}, name="search_results")
     */
    public function searchResults($page, Request $request, VideoForNoValidSubscription $video_no_members)
    {
        $videos = null;
        $query = null;

        if($query = $request->get('query'))
        {
            $videos = $this->getDoctrine()
            ->getRepository(Video::class)
            ->findByTitle($query, $page, $request->get('sortby'));

            if(!$videos->getItems()) $videos = null;
        }
       
        return $this->render('front/search_results.html.twig',[
            'videos' => $videos,
            'query' => $query,
            'video_no_members' => $video_no_members->check()
        ]);
    }


    /**
     * @Route("/pricing", name="pricing")
     */
    public function pricing()
    {
        return $this->render('front/pricing.html.twig');
    }

    /**
     * @Route("/register/{plan}", name="register")
     */
    public function register(UserPasswordEncoderInterface $passwordEncoder, Request $request, SessionInterface $session,$plan)
    {
        if($request->isMethod('get'))
        {
            $session->set('planName', $plan);
            $session->set('planPrice', Subscription::getPlanDataPriceByName($plan));
        }


        $user = new User();
        $form = $this->createForm(UserType::class, $user);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid())
        {
            $em = $this->getDoctrine()->getManager();
            $user->setName($request->request->get('user')['name']);
            $user->setLastName($request->request->get('user')['last_name']);
            $user->setEmail($request->request->get('user')['email']);

            $password = $passwordEncoder->encodePassword($user, $request->request->get('user')['password']['first']);
            $user->setPassword($password);

            $user->setRoles(['ROLE_USER']);

            $date = new \DateTime();
            $date->modify('+1 month');
            $subscription = new Subscription();
            $subscription->setValidTo($date);
            $subscription->setPlan($session->get('planName'));

            if($plan == Subscription::getPlanDataNameByIndex(0))
            {
                $subscription->setFreePlanUsed(true);
                $subscription->setPaymentStatus('paid');
                
            }else {
                $subscription->setFreePlanUsed(false);

            }

            $em->persist($subscription);

            $user->setSubscription($subscription);

            
            $em->persist($user);
            $em->flush();
            
            $this->loginUserAuto($user, $password);
            
            return $this->redirectToRoute('admin_main_page');

        }
        if($this->isGranted('IS_AUTHENTICATED_REMEMBERED') && $plan == Subscription::getPlanDataNameByIndex(0)) // free plan
        {
            // to do save subscription
            $this->saveSubscription($plan, $this->getUser());
            return $this->redirectToRoute('admin_main_page');
            
        }
        elseif($this->isGranted('IS_AUTHENTICATED_REMEMBERED'))
        {
            return $this->redirectToRoute('payment');
        }

        return $this->render('front/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(AuthenticationUtils $helper)
    {
        return $this->render('front/login.html.twig', [
            'error' => $helper->getLastAuthenticationError()
        ]);
    }

    private function loginUserAuto($user , $password)
    {
        $token = new UsernamePasswordToken($user, $password, 'main', $user->getRoles());
        $this->get('security.token_storage')->setToken($token);
        $this->get('session')->set('_security_main', serialize($token));
          
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout(): void
    {
        throw new \Exception('this should never be reached!');
    }
    /**
     * @Route("/payment/{paypal}", name="payment", defaults={"paypal":false})
     */
    public function payment($paypal, SessionInterface $session)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        if($paypal){
            $this->saveSubscription($session->get('planName'), $this->getUser());
            return $this->redirectToRoute('admin_main_page');
        }
        return $this->render('front/payment.html.twig');
    }

    // this method its called on the base.html.twig 
    public function mainCategories()
    {
        $categories = $this->getDoctrine()
                    ->getRepository(Category::class)
                    ->findBy(['parent' => null], ['name' => 'ASC']);
        return $this->render('front/_main_categories.html.twig', [
            'categories' => $categories,
        ]);
    }

    /**
     * @Route("/new-comment/{video}", methods={"POST"}, name="new_comment")
     */
    public function newComment(Video $video, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        if(!empty(trim($request->request->get('comment'))))
        {
            $em = $this->getDoctrine()->getManager();
            $comment = new Comment();
            $comment->setContent($request->request->get('comment'));
            $comment->setVideo($video);
            $comment->setUser($this->getUser());
            $em->persist($comment);
            $em->flush();
        }

        return $this->redirectToRoute('video_details', [
            'video' => $video->getId()
        ]);

    }

    /**
    * @Route("/delete-comment/{comment}", name="delete_comment")
    * @Security("user.getId() == comment.getUser().getId()")
    */
    public function deleteComment(Comment $comment, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $em = $this->getDoctrine()->getManager();
        $em->remove($comment);
        $em->flush();

        return $this->redirect($request->headers->get('referer'));
    }

    /**
     * @Route("/video-list/{video}/like", name="like_video", methods={"POST"})
     * @Route("/video-list/{video}/dislike", name="dislike_video", methods={"POST"})
     * @Route("/video-list/{video}/unlike", name="undo_like_video", methods={"POST"})
     * @Route("/video-list/{video}/undodislike", name="undo_dislike_video", methods={"POST"})
     */
    public function toggleLikesAjax(Video $video, Request $request)
    {
        
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        switch($request->get('_route'))
        {
            case 'like_video':
            $result = $this->likeVideo($video);
            break;
            
            case 'dislike_video':
            $result = $this->dislikeVideo($video);
            break;

            case 'undo_like_video':
            $result = $this->undoLikeVideo($video);
            break;

            case 'undo_dislike_video':
            $result = $this->undoDislikeVideo($video);
            break;
        }

        return $this->json(['action' => $result,'id'=>$video->getId()]);
    }

    private function likeVideo($video)
    {  
        $user = $this->getDoctrine()->getRepository(User::class)->find($this->getUser());
        $user->addLikedVideo($video);

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush(); 
        return 'liked';
    }
    private function dislikeVideo($video)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->find($this->getUser());
        $user->addDislikedVideo($video);

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush(); 
        return 'disliked';
    }
    private function undoLikeVideo($video)
    {  
        $user = $this->getDoctrine()->getRepository(User::class)->find($this->getUser());
        $user->removeLikedVideo($video);

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush(); 
        return 'undo liked';
    }
    private function undoDislikeVideo($video)
    {   
        $user = $this->getDoctrine()->getRepository(User::class)->find($this->getUser());
        $user->removeDislikedVideo($video);

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();
        return 'undo disliked';
    }
}
