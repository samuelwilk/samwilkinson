<?php

namespace App\Controller\Admin;

use App\Entity\Post;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PostCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Post::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Post')
            ->setEntityLabelInPlural('Studio Posts')
            ->setSearchFields(['title', 'excerpt', 'tags', 'slug'])
            ->setDefaultSort(['publishedAt' => 'DESC'])
            ->setPaginatorPageSize(30);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex();

        yield TextField::new('title')
            ->setRequired(true)
            ->setHelp('Post title displayed on /studio page');

        yield SlugField::new('slug')
            ->setTargetFieldName('title')
            ->setRequired(true)
            ->setHelp('URL-friendly identifier (auto-generated from title)');

        yield TextareaField::new('excerpt')
            ->setHelp('Short preview shown on /studio index (1-2 sentences)')
            ->hideOnIndex();

        yield TextareaField::new('content')
            ->setRequired(true)
            ->setHelp('Full post content in Markdown format')
            ->hideOnIndex();

        yield ArrayField::new('tags')
            ->setHelp('Post tags (e.g., "development", "photography", "process")')
            ->hideOnIndex();

        yield BooleanField::new('isPublished')
            ->setLabel('Published')
            ->setHelp('Only published posts appear on /studio page');

        yield DateTimeField::new('publishedAt')
            ->setHelp('Publication date (used for "Last updated Q[N] YYYY" on homepage)')
            ->hideOnIndex();

        yield IntegerField::new('readingTimeMinutes')
            ->setLabel('Reading Time (minutes)')
            ->setHelp('Estimated reading time in minutes (auto-calculated if left blank)')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt')
            ->onlyOnDetail();

        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();
    }
}
