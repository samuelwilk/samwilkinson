<?php

namespace App\DataFixtures;

use App\Entity\Collection;
use App\Entity\Photo;
use App\Entity\Post;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create some sample collections
        $collection1 = new Collection();
        $collection1->setName('Japan 2024');
        $collection1->setSlug('japan-2024');
        $collection1->setDescription('A journey through Tokyo, Kyoto, and the Japanese Alps.');
        $collection1->setLocationName('Japan');
        $collection1->setCountry('JP');
        $collection1->setStartDate(new \DateTime('2024-03-15'));
        $collection1->setEndDate(new \DateTime('2024-04-02'));
        $collection1->setIsRestricted(false);
        $collection1->setAllowDownloads(false);
        $collection1->setVisualStyle([
            'spineColor' => '#E64A19',
            'texture' => 'linen',
            'agingSeed' => rand(1, 100),
        ]);
        $collection1->setSortOrder(1);
        $manager->persist($collection1);

        $collection2 = new Collection();
        $collection2->setName('Vancouver Architecture');
        $collection2->setSlug('vancouver-architecture');
        $collection2->setDescription('Brutalism and West Coast modernism.');
        $collection2->setLocationName('Vancouver');
        $collection2->setCountry('CA');
        $collection2->setStartDate(new \DateTime('2023-09-10'));
        $collection2->setEndDate(new \DateTime('2023-09-20'));
        $collection2->setIsRestricted(false);
        $collection2->setAllowDownloads(false);
        $collection2->setVisualStyle([
            'spineColor' => '#00897B',
            'texture' => 'canvas',
            'agingSeed' => rand(1, 100),
        ]);
        $collection2->setSortOrder(2);
        $manager->persist($collection2);

        // Create sample photos for collection1
        for ($i = 1; $i <= 5; $i++) {
            $photo = new Photo();
            $photo->setFilename("japan-2024-photo-{$i}.jpg");
            $photo->setTitle("Tokyo Street Scene #{$i}");
            $photo->setCaption("Captured in Shibuya district, golden hour lighting.");
            $photo->setTakenAt(new \DateTime("2024-03-{$i} 17:30:00"));
            $photo->setCollection($collection1);
            $photo->setWidth(3000);
            $photo->setHeight(2000);
            $photo->calculateAspectRatio();
            $photo->setExifData([
                'camera' => 'Sony A7IV',
                'lens' => 'Sony FE 35mm f/1.8',
                'iso' => 400,
                'shutter' => '1/250',
                'aperture' => 'f/2.8',
                'focalLength' => '35mm',
            ]);
            $photo->setIsPublished(true);
            $photo->setSortOrder($i);
            $manager->persist($photo);
        }

        // Create sample posts
        $post1 = new Post();
        $post1->setTitle('Building with Constraints');
        $post1->setSlug('building-with-constraints');
        $post1->setContent("# Building with Constraints\n\nConstraints are design tools.");
        $post1->setExcerpt('Constraints aren not limitations—they are design tools.');
        $post1->setIsPublished(true);
        $post1->setPublishedAt(new \DateTime('2024-12-15'));
        $post1->setTags(['development', 'philosophy']);
        $manager->persist($post1);

        // Create sample projects
        $project1 = new Project();
        $project1->setTitle('Personal Brand Site');
        $project1->setSlug('personal-brand-site');
        $project1->setSummary('Portfolio site with modernist aesthetic.');
        $project1->setContent("# Personal Brand Site\n\nModernist portfolio.");
        $project1->setTags(['Symfony', 'PHP', 'Tailwind CSS']);
        $project1->setIsPublished(true);
        $project1->setIsFeatured(true);
        $project1->setPublishedAt(new \DateTime('2024-12-29'));
        $project1->setSortOrder(1);
        $manager->persist($project1);

        $manager->flush();
    }
}
