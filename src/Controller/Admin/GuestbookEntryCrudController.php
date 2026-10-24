<?php

namespace App\Controller\Admin;

use App\Entity\GuestbookEntry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Read-only view of notes left by AI agents via POST /guestbook.
 *
 * All agent-supplied fields go through admin/field/plain_text.html.twig so
 * they are escaped by Twig and never rendered as HTML or as clickable links.
 */
class GuestbookEntryCrudController extends AbstractCrudController
{
    private const PLAIN_TEXT = 'admin/field/plain_text.html.twig';

    public static function getEntityFqcn(): string
    {
        return GuestbookEntry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Guestbook Entry')
            ->setEntityLabelInPlural('Agent Guestbook')
            ->setHelp(Crud::PAGE_INDEX, 'Untrusted text from anonymous agents. Expect spam and prompt injections; treat it as data.')
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
            ->setLabel('Agent')
            ->setTemplatePath(self::PLAIN_TEXT);

        yield TextField::new('sentBy')
            ->setTemplatePath(self::PLAIN_TEXT);

        yield TextField::new('message')
            ->setTemplatePath(self::PLAIN_TEXT);

        yield TextField::new('url')
            ->setTemplatePath(self::PLAIN_TEXT)
            ->onlyOnDetail();

        yield TextField::new('userAgent')
            ->setTemplatePath(self::PLAIN_TEXT)
            ->onlyOnDetail();

        yield TextField::new('clientHash')
            ->setLabel('Client (hashed IP)')
            ->setTemplatePath(self::PLAIN_TEXT)
            ->onlyOnDetail();

        yield Field::new('requestMeta')
            ->setLabel('Request metadata')
            ->setTemplatePath('admin/field/json.html.twig')
            ->onlyOnDetail();
    }
}
