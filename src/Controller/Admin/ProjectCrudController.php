<?php

namespace App\Controller\Admin;

use App\Entity\Project;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ProjectCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Project::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Project')
            ->setEntityLabelInPlural('Projects')
            ->setSearchFields(['title', 'summary', 'tags', 'slug'])
            ->setDefaultSort(['sortOrder' => 'ASC', 'publishedAt' => 'DESC'])
            ->setPaginatorPageSize(30);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex();

        yield TextField::new('title')
            ->setRequired(true)
            ->setHelp('Project title displayed on /build page');

        yield SlugField::new('slug')
            ->setTargetFieldName('title')
            ->setRequired(true)
            ->setHelp('URL-friendly identifier (auto-generated from title)');

        yield TextareaField::new('summary')
            ->setHelp('Short description shown in project card (1-2 sentences)')
            ->hideOnIndex();

        yield TextareaField::new('content')
            ->setRequired(true)
            ->setHelp('Full case study content in Markdown format')
            ->hideOnIndex();

        yield ArrayField::new('tags')
            ->setHelp('Technology tags (e.g., "Symfony", "Docker", "AWS")')
            ->hideOnIndex();

        yield UrlField::new('url')
            ->setHelp('Live project URL (if applicable)')
            ->hideOnIndex();

        yield UrlField::new('githubUrl')
            ->setLabel('GitHub URL')
            ->setHelp('GitHub repository URL (if applicable)')
            ->hideOnIndex();

        yield TextField::new('thumbnailImage')
            ->setHelp('Path to thumbnail image for project card')
            ->hideOnIndex();

        yield BooleanField::new('isPublished')
            ->setLabel('Published')
            ->setHelp('Only published projects appear on /build page');

        yield BooleanField::new('isFeatured')
            ->setLabel('Featured')
            ->setHelp('Featured projects appear at top of /build page')
            ->hideOnIndex();

        yield DateTimeField::new('publishedAt')
            ->setHelp('Publication date (used for "Last updated Q[N] YYYY" on homepage)')
            ->hideOnIndex();

        yield IntegerField::new('sortOrder')
            ->setLabel('Sort Order')
            ->setHelp('Lower numbers appear first (0 = top, 999 = bottom)')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt')
            ->onlyOnDetail();

        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();
    }
}
