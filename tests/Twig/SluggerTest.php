<?php

namespace App\Tests\Twig;

use PHPUnit\Framework\TestCase;
use App\Twig\AppExtension;

class SluggerTest extends TestCase
{
    /**
     * @dataProvider getSlugs
     */
    public function testSlugify(string $string, string $slugy)
    {
        $slugger = new AppExtension;

        $this->assertSame($slugy, $slugger->slugify($string));
    }

    public function getSlugs()
    {
        
        yield ['Lorem Ipsum', 'lorem-ipsum'];
        yield [' Lorem Ipsum', 'lorem-ipsum'];
        yield ['LOrEm iPsUm', 'lorem-ipsum'];
        yield ['!Lorem Ipsum!', 'lorem-ipsum'];
        yield ['lorem-ipsum', 'lorem-ipsum'];
        yield ['Children\'s books', 'childrens-books'];
        yield ['Five star movies', 'five-star-movies'];
        yield ['Adults 60+', 'adults-60'];
        
    }
}
