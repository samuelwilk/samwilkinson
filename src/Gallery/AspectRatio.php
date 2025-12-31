<?php

declare(strict_types=1);

namespace App\Gallery;

/**
 * Aspect ratio classification for photos and patterns.
 *
 * Used to match photos with appropriate layout patterns based on their
 * dimensions and orientation.
 */
enum AspectRatio: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';
    case Square = 'square';
    case Any = 'any';
}
