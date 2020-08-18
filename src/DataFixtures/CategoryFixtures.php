<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $this->loadMainCategories($manager);
        $this->loadElectronics($manager);
        $this->loadComputer($manager);
        $this->loadLaptob($manager);
        $this->loadBooks($manager);
        $this->loadMovies($manager);
        $this->loadCommedy($manager);

    }
    private function loadMainCategories($manager)
    {
        foreach($this->getMainCategoriesData() as [$name] ) {
            $category = new Category();
            $category->setName($name);
            $manager->persist($category);

            $manager->flush();
        }
        
    }
    private function loadElectronics($manager) {
        $this->loadSubcategories($manager, 'Electronics', 1);
    }
    private function loadComputer($manager) {
        $this->loadSubcategories($manager, 'Computer', 6);
    }
    private function loadLaptob($manager) {
        $this->loadSubcategories($manager, 'Laptob', 8);
    }
    private function loadBooks($manager) {
        $this->loadSubcategories($manager, 'Books', 3);
    }
    private function loadMovies($manager) {
        $this->loadSubcategories($manager, 'Movies', 4);
    }
    private function loadCommedy($manager) {
        $this->loadSubcategories($manager, 'Commedy', 17);
    }
    private function loadSubcategories($manager, $category, $parent_id) {
        $parent = $manager->getRepository(Category::class)->find($parent_id);
        $methodName = "get{$category}Data";
        foreach($this->$methodName() as [$name] ) {

            $category = new Category();
            $category->setName($name);
            $category->setParent($parent);
            $manager->persist($category);

            $manager->flush();
        }
    }

    private function getMainCategoriesData(){
        return [
            ['Electronics', 1],
            ['Toys', 2],
            ['Books', 3],
            ['Movies', 4]
        ];
    }

    private function getElectronicsData(){
        return [
            ['Camera', 5],
            ['Computer',6],
            ['Cell Phones', 7]
        ];
    }
    private function getComputerData(){
        return [
            ['Laptob', 8],
            ['Desktop',9]
        ];
    }
    private function getLaptobData(){
        return [
            ['Asus', 10],
            ['Hp',11],
            ['Apple', 12]
        ];
    }
    private function getBooksData(){
        return [
            ['Children\'s Book', 13],
            ['Kindle ebook',14],
            ['The men book', 15]
        ];
    }
    private function getMoviesData(){
        return [
            ['Scary', 16],
            ['Commedy',17],
            ['Adventures', 18]
        ];
    }
    private function getCommedyData(){
        return [
            ['Lucifer', 19],
            ['Blacklist',20]
        ];
    }
  
}
