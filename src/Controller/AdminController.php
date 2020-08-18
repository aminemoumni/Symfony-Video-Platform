<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Video;
use App\Form\UserType;
use App\Form\VideoType;
use App\Entity\Category;
use App\Form\CategoryType;
use App\Utils\CategoryTreeAdminList;
use App\Utils\CategoryTreeAdminOptionList;
use App\Utils\Interfaces\UploaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @Route("/admin")
 */
class AdminController extends AbstractController
{
    /**
     * @Route("/", name="admin_main_page")
     */
    public function index(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $user =  $this->getUser();
        $form = $this->createForm(UserType::class, $user, ['user' => $user]);
        $form->handleRequest($request);
        $is_invalid = null;

        if($form->isSubmitted() && $form->isValid())
        {
            $em = $this->getDoctrine()->getManager();
            $user->setName($request->request->get('user')['name']);
            $user->setLastName($request->request->get('user')['last_name']);
            $user->setEmail($request->request->get('user')['email']);

            if($request->request->get('user')['password']['first'])
            {
                $password = $passwordEncoder->encodePassword($user, $request->request->get('user')['password']['first']);
                $user->setPassword($password);
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Your changes were saved!');
            return $this->redirectToRoute('admin_main_page');
        }
        else {
            $is_invalid = 'is-invalid';
        }

        return $this->render('admin/my_profile.html.twig', [
            'subscription' => $this->getUser()->getSubscription(),
            'form' => $form->createView(),
            'is_invalid' => $is_invalid
        ]);
    }

    /**
     * @Route("/su/categories", name="categories", methods={"GET", "POST"})
     */
    public function categories(CategoryTreeAdminList $categories, Request $request)
    {
        $categories->getCategoryList($categories->buildTree());
        
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);

        if($this->saveCategory($category, $form, $request))
        {
            return $this->redirectToRoute('categories');
        }

        return $this->render('admin/categories.html.twig', [
            'categories' => $categories,
            'form' => $form->createView(),
        ]);
    }
    /**
     * @Route("/su/edit-category/{id}", name="edit_category", methods={"GET", "POST"})
     */
    public function editCategory(Category $category, Request $request)
    {
        $form = $this->createForm(CategoryType::class, $category);
        
        if($this->saveCategory($category, $form, $request))
        {
            return $this->redirectToRoute('categories');
        }
        return $this->render('admin/edit_category.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }
    /**
     * @Route("/su/delete-category/{id}", name="delete_category")
     */
    public function deleteCategory(Category $category)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($category);
        $em->flush();
        return $this->redirectToRoute('categories');
    }


    /**
     * @Route("/videos", name="videos")
     */
    public function videos(CategoryTreeAdminOptionList $categories)
    {

        if ($this->isGranted('ROLE_ADMIN')) 
        {

            $categories->getCategoryList($categories->buildTree());
            $videos = $this->getDoctrine()->getRepository(Video::class)->findBy([],['title'=>'ASC']);
            
        }
        else
        {
            $categories = null;
            $videos = $this->getUser()->getLikedVideos();
        }

        return $this->render('admin/videos.html.twig',[
            'videos'=>$videos,
            'categories'=>$categories
        ]);
    }

    /**
     * @Route("/su/upload-video-locally", name="upload_video_locally")
     */
    public function uploadVideoLocally(Request $request, UploaderInterface $fileUploader)
    {
        $video = new Video();
        $form = $this->createForm(VideoType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $em = $this->getDoctrine()->getManager();

            $file = $video->getUploadedVideo();
            $fileName = $fileUploader->upload($file);

            $base_path = Video::uploadFolder;
            $video->setPath($base_path.$fileName[0]);
            $video->setTitle($fileName[1]);
           
            $em->persist($video);
            $em->flush();

            return $this->redirectToRoute('videos');
        }


        return $this->render('admin/upload_video_locally.html.twig',[
            'form' => $form->createView()
        ]);
    }
     /**
     * @Route("/delete-video/{video}/{path}", name="delete_video", requirements={"path"=".+"})
     */
    public function deleteVideo(Video $video, $path, UploaderInterface $fileUploader)
    {

        $em = $this->getDoctrine()->getManager();
        $em->remove($video);
        $em->flush();

        if( $fileUploader->delete($path) )
        {
            $this->addFlash(
                'success',
                'The video was successfully deleted.'
            );
        }
        else
        {
            $this->addFlash(
                'danger',
                'We were not able to delete. Check the video.'
            );
        }
        
        return $this->redirectToRoute('videos');

    }
    /**
     * @Route("/update-video-category/{video}", methods={"POST"}, name="update_video_category")
    */
    public function updateVideoCategory(Request $request, Video $video)
     {

        $em = $this->getDoctrine()->getManager();

        $category = $this->getDoctrine()->getRepository(Category::class)->find($request->request->get('video_category'));

        $video->setCategory($category);

        $em->persist($video);
        $em->flush();
 
        return $this->redirectToRoute('videos');
     }



    /**
     * @Route("/su/users", name="users")
     */
    public function users()
    {
        $repository = $this->getDoctrine()->getRepository(User::class);
        $users = $repository->findBy([], ['name' => 'ASC']);
        return $this->render('admin/users.html.twig', [
            'users' => $users,
        ]);
    }



    public function getAllCategories(CategoryTreeAdminOptionList $categories, $editedCategory = null)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $categories->getCategoryList($categories->buildTree());

        return $this->render('admin/_all_categories.html.twig',[
            'categories' => $categories,
            'editedCategory' => $editedCategory
        ]);
    }


    private function saveCategory($category, $form, $request)
    {
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) 
        {
            $category->setName($request->request->get('category')['name']);
            $repository = $this->getDoctrine()->getRepository(Category::class);
            $parent = $repository->find($request->request->get('category')['parent']);
            $category->setParent($parent);

            $em = $this->getDoctrine()->getManager();
            $em->persist($category);
            $em->flush();

            return true;
        }

        return false;
    }

    /**
     * @Route("/cancel-plan", name="cancel_plan")
     */
    public function cancelPlan(){
        $subscription = $this->getUser()->getSubscription();
        $subscription->setValidTo(new \DateTime());
        $subscription->setPaymentStatus(null);
        $subscription->setPlan('canceled');

        $em = $this->getDoctrine()->getManager();
        $em->persist($subscription);
        $em->flush();

        return $this->redirectToRoute('admin_main_page');
    }


    /**
     * @Route("/delete-account", name="delete_account")
     */
    public function deleteAccount()
    {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();

        $em->remove($user);
        $em->flush();

        session_destroy();
        return $this->redirectToRoute('main_page');
    }

    
    /**
     * @Route("/delete-user/{user}", name="delete_user")
     */
    public function deleteUser(User $user)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($user);
        $em->flush();

        return $this->redirectToRoute('users');
    }
}
