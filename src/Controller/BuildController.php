<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BuildController extends AbstractController
{
    #[Route('/build', name: 'app_build')]
    public function index(): Response
    {
        // TODO: Fetch projects from database
        $projects = [];

        return $this->render('build/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/build/{slug}', name: 'app_build_show', requirements: ['slug' => '[a-z0-9-]+'])]
    public function show(string $slug): Response
    {
        // TODO: Fetch project by slug from database
        $project = null;

        return $this->render('build/show.html.twig', [
            'project' => $project,
            'slug' => $slug,
        ]);
    }
}
