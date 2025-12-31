<?php

namespace App\Controller\Admin;

use App\Entity\Photo;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Vich\UploaderBundle\Form\Type\VichImageType;

class PhotoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Photo::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('collection')->setLabel('Collection'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),

            TextField::new('imageFile')
                ->setFormType(VichImageType::class)
                ->setFormTypeOptions([
                    'required' => false,
                    'allow_delete' => false,
                ])
                ->setLabel('Upload Photo')
                ->setHelp('Leave empty to keep existing photo')
                ->onlyOnForms(),

            ImageField::new('filename')
                ->setBasePath('/uploads/photos')
                ->setLabel('Photo')
                ->hideOnForm(),

            TextField::new('title')
                ->setLabel('Title'),

            TextareaField::new('caption')
                ->setLabel('Caption')
                ->hideOnIndex(),

            AssociationField::new('collection')
                ->setLabel('Collection'),

            DateTimeField::new('takenAt')
                ->setLabel('Date Taken')
                ->hideOnIndex(),

            IntegerField::new('width')
                ->hideOnForm()
                ->hideOnIndex(),

            IntegerField::new('height')
                ->hideOnForm()
                ->hideOnIndex(),

            BooleanField::new('isPublished')
                ->setLabel('Published'),

            BooleanField::new('useForIndexCover')
                ->setLabel('Use for Index Cover')
                ->setHelp('Mark this photo to be used as the cover on the collection index page. Only one photo per collection can have this enabled.')
                ->hideOnIndex(),

            IntegerField::new('sortOrder')
                ->setLabel('Sort Order')
                ->hideOnIndex(),

            DateTimeField::new('uploadedAt')
                ->setLabel('Uploaded')
                ->hideOnForm()
                ->hideOnIndex(),

            DateTimeField::new('updatedAt')
                ->setLabel('Updated')
                ->hideOnForm()
                ->hideOnIndex(),
        ];
    }
}
