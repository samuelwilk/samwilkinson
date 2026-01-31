<?php

namespace App\Controller\Admin;

use App\Entity\SocialLink;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class SocialLinkCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SocialLink::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Social Link')
            ->setEntityLabelInPlural('Social Links')
            ->setSearchFields(['platform', 'url'])
            ->setDefaultSort(['sortOrder' => 'ASC'])
            ->setPaginatorPageSize(30);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex();

        yield TextField::new('platform')
            ->setRequired(true)
            ->setHelp('Platform name (e.g., "Instagram", "LinkedIn", "GitHub")');

        yield UrlField::new('url')
            ->setRequired(true)
            ->setHelp('Full URL to your profile (e.g., "https://instagram.com/username")');

        yield ChoiceField::new('icon')
            ->setRequired(true)
            ->setChoices([
                'Instagram' => 'instagram',
                'LinkedIn' => 'linkedin',
                'GitHub' => 'github',
                'Twitter/X' => 'twitter',
                'Facebook' => 'facebook',
                'YouTube' => 'youtube',
                'Dribbble' => 'dribbble',
                'Behance' => 'behance',
                'Email' => 'email',
                'Website' => 'globe',
            ])
            ->setHelp('Icon to display for this link');

        yield IntegerField::new('sortOrder')
            ->setHelp('Display order (lower numbers appear first)')
            ->setFormTypeOption('attr', ['min' => 0, 'step' => 1]);

        yield BooleanField::new('isVisible')
            ->setLabel('Visible')
            ->setHelp('Only visible links appear in the footer');
    }
}
