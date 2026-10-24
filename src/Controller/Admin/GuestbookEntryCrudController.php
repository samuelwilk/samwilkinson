<?php

namespace App\Controller\Admin;

use App\Entity\GuestbookEntry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

/**
 * Read-only view of notes left by AI agents via POST /guestbook.
 */
class GuestbookEntryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GuestbookEntry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Guestbook Entry')
            ->setEntityLabelInPlural('Agent Guestbook')
            ->setSearchFields(['agentName', 'sentBy', 'message', 'userAgent'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex();

        yield DateTimeField::new('createdAt')
            ->setLabel('Received');

        yield TextField::new('agentName')
            ->setLabel('Agent');

        yield TextField::new('sentBy');

        yield TextareaField::new('message')
            ->setMaxLength(120)
            ->onlyOnIndex();

        yield TextareaField::new('message')
            ->onlyOnDetail();

        yield UrlField::new('url')
            ->onlyOnDetail();

        yield TextField::new('userAgent')
            ->onlyOnDetail();
    }
}
