<?php

namespace App\Controller;

use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StudioController extends AbstractController
{
    #[Route('/studio', name: 'app_studio')]
    public function index(PostRepository $postRepository): Response
    {
        // Fetch published posts, ordered by most recent first
        $posts = $postRepository->findBy(
            ['isPublished' => true],
            ['publishedAt' => 'DESC'],
            20 // Limit to 20 most recent posts for performance
        );

        // Serialize posts for Three.js scene
        $postsData = array_map(function ($post) {
            return [
                'title' => $post->getTitle(),
                'slug' => $post->getSlug(),
                'excerpt' => $post->getExcerpt() ? substr($post->getExcerpt(), 0, 150) : '',
                'date' => $post->getPublishedAt()?->format('Y-m-d'),
                'readingTime' => $post->getReadingTimeMinutes() . ' min read',
                'tags' => $post->getTags() ?? [],
                'url' => $this->generateUrl('app_studio_show', ['slug' => $post->getSlug()]),
            ];
        }, $posts);

        return $this->render('studio/index.html.twig', [
            'posts' => $postsData,
        ]);
    }

    #[Route('/studio/{slug}', name: 'app_studio_show', requirements: ['slug' => '[a-z0-9-]+'])]
    public function show(string $slug, PostRepository $postRepository): Response
    {
        // Fetch post by slug from database
        $post = $postRepository->findOneBy([
            'slug' => $slug,
            'isPublished' => true
        ]);

        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        return $this->render('studio/show.html.twig', [
            'post' => $post,
            'slug' => $slug,
        ]);
    }
}
