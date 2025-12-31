<?php

namespace App\Controller\Admin;

use App\Entity\Collection;
use App\Entity\Photo;
use App\Entity\Post;
use App\Entity\Project;
use App\Repository\CollectionRepository;
use App\Repository\PhotoRepository;
use App\Repository\PostRepository;
use App\Repository\ProjectRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly CollectionRepository $collectionRepository,
        private readonly PhotoRepository $photoRepository,
        private readonly PostRepository $postRepository,
        private readonly ProjectRepository $projectRepository,
    ) {
    }

    public function index(): Response
    {
        // Get counts for dashboard stats
        $stats = [
            'collections' => $this->collectionRepository->count([]),
            'photos' => $this->photoRepository->count([]),
            'publishedPhotos' => $this->photoRepository->count(['isPublished' => true]),
            'posts' => $this->postRepository->count([]),
            'publishedPosts' => $this->postRepository->count(['isPublished' => true]),
            'projects' => $this->projectRepository->count([]),
            'publishedProjects' => $this->projectRepository->count(['isPublished' => true]),
        ];

        // Get recent items
        $recentPhotos = $this->photoRepository->findBy([], ['uploadedAt' => 'DESC'], 5);
        $recentPosts = $this->postRepository->findBy([], ['updatedAt' => 'DESC'], 5);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recentPhotos' => $recentPhotos,
            'recentPosts' => $recentPosts,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Sam Wilkinson - Admin');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addAssetMapperEntry('admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Photography');
        yield MenuItem::linkToCrud('Collections', 'fa fa-folder', Collection::class);
        yield MenuItem::linkToCrud('Photos', 'fa fa-camera', Photo::class);

        yield MenuItem::section('Content');
        yield MenuItem::linkToCrud('Projects', 'fa fa-code', Project::class);
        yield MenuItem::linkToCrud('Posts', 'fa fa-file-text', Post::class);

        yield MenuItem::section('Site');
        yield MenuItem::linkToRoute('Back to Site', 'fa fa-arrow-left', 'app_home');
    }
}
