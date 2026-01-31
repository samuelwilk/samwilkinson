<?php

namespace App\EventSubscriber;

use App\Repository\SocialLinkRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class TwigGlobalSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly SocialLinkRepository $socialLinkRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        // Only process main requests
        if (!$event->isMainRequest()) {
            return;
        }

        // Inject social links into all Twig templates
        $socialLinks = $this->socialLinkRepository->findVisible();
        $this->twig->addGlobal('socialLinks', $socialLinks);
    }
}
