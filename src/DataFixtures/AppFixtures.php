<?php

namespace App\DataFixtures;

use App\Entity\Collection;
use App\Entity\Photo;
use App\Entity\Post;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create admin user for local development
        $admin = new User();
        $admin->setEmail('admin@samwilkinson.local');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'admin')
        );
        $manager->persist($admin);

        // Create sample collections
        $collection1 = new Collection();
        $collection1->setName('Japan 2024');
        $collection1->setSlug('japan-2024');
        $collection1->setDescription('A journey through Tokyo, Kyoto, and the Japanese Alps.');
        $collection1->setLocationName('Japan');
        $collection1->setCountry('JP');
        $collection1->setStartDate(new \DateTime('2024-03-15'));
        $collection1->setEndDate(new \DateTime('2024-04-02'));
        $collection1->setVisualStyle([
            'spineColor' => '#E64A19',
            'texture' => 'linen',
        ]);
        $collection1->setSortOrder(1);
        $manager->persist($collection1);

        // Create sample photos
        for ($i = 1; $i <= 5; $i++) {
            $photo = new Photo();
            $photo->setFilename("japan-{$i}.jpg");
            $photo->setTitle("Tokyo Scene #{$i}");
            $photo->setCaption("Golden hour in Shibuya.");
            $photo->setTakenAt(new \DateTime("2024-03-{$i} 17:30:00"));
            $photo->setCollection($collection1);
            $photo->setWidth(3000);
            $photo->setHeight(2000);
            $photo->calculateAspectRatio();
            $photo->setExifData([
                'camera' => 'Sony A7IV',
                'lens' => 'Sony FE 35mm f/1.8',
                'iso' => 400,
            ]);
            $photo->setIsPublished(true);
            $manager->persist($photo);
        }

        // Create sample post
        $post1 = new Post();
        $post1->setTitle('Building with Constraints');
        $post1->setSlug('building-with-constraints');
        $post1->setContent("# Building with Constraints\n\nConstraints are design tools.");
        $post1->setExcerpt('Constraints are design tools.');
        $post1->setIsPublished(true);
        $post1->setTags(['development', 'philosophy']);
        $manager->persist($post1);

        // Create sample project
        $project1 = new Project();
        $project1->setTitle('Personal Brand Site');
        $project1->setSlug('personal-brand-site');
        $project1->setSummary('Portfolio site with modernist aesthetic.');
        $project1->setContent("# Personal Brand Site\n\nModernist portfolio.");
        $project1->setTags(['Symfony', 'PHP', 'Tailwind']);
        $project1->setIsPublished(true);
        $project1->setIsFeatured(true);
        $project1->setSortOrder(1);
        $manager->persist($project1);

        $manager->flush();
    }
}
