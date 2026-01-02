<?php

namespace App\Controller;

use App\Repository\PhotoRepository;
use App\Repository\PostRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        ProjectRepository $projectRepository,
        PhotoRepository $photoRepository,
        PostRepository $postRepository
    ): Response {
        // Get last published items for status display
        $lastProject = $projectRepository->findOneBy(
            ['isPublished' => true],
            ['publishedAt' => 'DESC']
        );

        $lastPhoto = $photoRepository->findOneBy(
            ['isPublished' => true],
            ['takenAt' => 'DESC']
        );

        $lastPost = $postRepository->findOneBy(
            ['isPublished' => true],
            ['publishedAt' => 'DESC']
        );

        return $this->render('home/index.html.twig', [
            'buildStatus' => $this->formatQuarter($lastProject?->getPublishedAt()),
            'stillsStatus' => $this->formatQuarter($lastPhoto?->getTakenAt()),
            'studioStatus' => $this->formatQuarter($lastPost?->getPublishedAt()),
        ]);
    }

    private function formatQuarter(?\DateTimeInterface $date): string
    {
        if (!$date) {
            return 'Coming soon';
        }

        $month = (int) $date->format('n');
        $year = $date->format('Y');
        $quarter = ceil($month / 3);

        return "Q{$quarter} {$year}";
    }
}
