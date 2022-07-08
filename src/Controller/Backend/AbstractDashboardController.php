<?php

namespace Base\Controller\Backend;

use App\Entity\User              as User;
use App\Entity\User\Group        as UserGroup;
use App\Entity\User\Notification as UserNotification;
use App\Entity\User\Permission   as UserPermission;
use App\Entity\User\Penalty      as UserPenalty;
use App\Entity\User\Token        as UserToken;

use App\Entity\Thread;
use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;
use App\Entity\Thread\Tag;

use App\Entity\Layout\Setting;
use App\Entity\Layout\Widget;
use App\Entity\User\Notification;
use App\Entity\Layout\Widget\Attachment;
use App\Entity\Layout\Widget\Set\Menu;
use App\Entity\Layout\Widget\Page;

use App\Controller\Backend\Crud\UserCrudController;
use Base\Entity\Layout\Image;
use Base\Backend\Config\WidgetItem;
use Base\Backend\Config\MenuItem;

use App\Enum\UserRole;
use Base\Annotations\Annotation\Iconize;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\ImageType;
use Base\Form\Type\Layout\SettingListType;
use Base\Form\Type\Layout\WidgetListType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use Base\Backend\Config\Extension;
use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;
use Base\Entity\Layout\Widget\Link;
use Base\Entity\Layout\Widget\Slot;
use Base\Service\Translator;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Request;

use Base\Backend\Config\Traits\WidgetTrait;
use Base\Entity\Extension\Log;
use Base\Entity\Extension\Revision;
use Base\Entity\Extension\Ordering;
use Base\Entity\Extension\TrashBall;
use Base\Entity\Layout\Short;
use Base\Entity\Thread\Taxon;
use Base\Field\Type\PasswordType;
use Base\Field\Type\RouteType;
use Base\Field\Type\SelectType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action as EaAction;
use Base\Backend\Config\Action;
use Base\Backend\Config\Actions;
use Base\Field\Type\BooleanType;
use Base\Routing\RouterInterface;
use Base\Service\IconProvider;
use Base\Service\ImageService;
use Base\Service\SettingBagInterface;
use Base\Twig\Environment;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/* "abstract" (remove because of routes) */
class AbstractDashboardController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController
{
    use WidgetTrait;

    protected $adminUrlGenerator;

    public const TRANSLATION_DASHBOARD = "backoffice";
    public const TRANSLATION_ENTITY    = "entities";
    public const TRANSLATION_ENUM      = "enums";

    public function __construct(
        Extension $extension,
        RequestStack $requestStack,
        TranslatorInterface $translator,
        AdminContextProvider $adminContextProvider,
        AdminUrlGenerator $adminUrlGenerator,
        RouterInterface $router,
        IconProvider $iconProvider,
        ImageService $imageService,
        Environment $twig,
        EntityManagerInterface $entityManager,
        SettingBagInterface $settingBag) {

        $this->extension = $extension;
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->adminContextProvider  = $adminContextProvider;

        $this->translator = $translator;
        WidgetItem::$translator = $translator;
        MenuItem::$translator = $translator;

        MenuItem::$router = $router;
        MenuItem::$iconProvider = $iconProvider;

        $this->twig              = $twig;
        $this->imageService      = $imageService;
        $this->settingBag        = $settingBag;
        $this->entityManager     = $entityManager;
        $this->router = $router;

        $this->settingRepository = $entityManager->getRepository(Setting::class);
        $this->widgetRepository  = $entityManager->getRepository(Widget::class);
        $this->slotRepository    = $entityManager->getRepository(Slot::class);
    }

    public function getExtension() { return $this->extension; }
    public function setExtension(Extension $extension)
    {
        $this->extension = $extension;
        return $this;
    }

    /**
     * Link to this controller to start the "connect" process
     *
     * @Route("/backoffice", name="backoffice")
     * @Iconize({"fas fa-fw fa-toolbox", "fas fa-fw fa-home"})
     */
    public function index(): Response
    {
        return $this->render('backoffice/index.html.twig');
    }

    /**
     * @Route("/backoffice/apikey", name="backoffice_apikey")
     * @Iconize({"fas fa-fw fa-fingerprint", "fas fa-fw fa-key"})
     */
    public function ApiKey(Request $request, array $fields = []): Response
    {
        $fields = array_reverse(array_merge(array_reverse([
            "api.spam.akismet" => [],
            "api.currency.fixer" => [],
            "api.currency.exchange_rates_api" => ["required" => false],
            "api.currency.currency_layer" => ["required" => false],
            "api.currency.abstract_api" => ["required" => false],
        ]), array_reverse($fields)));

        if(empty($fields))
            $fields = array_fill_keys($this->settingBag->getPaths("api"), []);

        foreach($fields as $key => $field) {

            if(!array_key_exists("form_type", $field)) $fields[$key]["form_type"] = PasswordType::class;
            if ($fields[$key]["form_type"] == PasswordType::class) {
                $fields[$key]["inline"]       = true;
                $fields[$key]["revealer"]     = true;
                $fields[$key]["repeater"]     = false;
                $fields[$key]["allow_empty"]  = true;
                $fields[$key]["min_length"]   = 0;
                $fields[$key]["max_strength"] = 0;
                $fields[$key]["secure"]       = false;
                $fields[$key]["hint"]         = false;
                $fields[$key]["autocomplete"] = false;
            }
        }

        $form = $this->createForm(SettingListType::class, null, ["fields" => $fields]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){

            $data     = array_filter($form->getData(), function($v, $k) use ($fields) {

                // If field is required but empty, update skip.. (to make sure the value is not empty)
                if($fields[$k]["required"] ?? true)
                    return !is_null($v) && $v->getValue() != null;

                // If not required, then we update regardless of the value, but checking for secure flag
                return !$fields[$k]["secure"];

            }, ARRAY_FILTER_USE_BOTH);

            $fields   = array_keys($form->getConfig()->getOption("fields"));
            $settings = array_transforms(
                fn($k,$s): ?array => $s === null ? null : [$s->getPath(), $s] ,
                $this->settingBag->getRawScalar($fields)
            );

            foreach($settings as $setting)
                $setting->setSecure(true);

            foreach(array_diff_key($data, $settings) as $name => $setting)
                $this->settingRepository->persist($setting);

            $this->settingRepository->flush();

            $notification = new Notification("@controllers.backoffice_apikey.success");
            $notification->setUser($this->getUser());
            $notification->send("success");

            return $this->router->reloadRequest();
        }

        return $this->render('backoffice/apikey.html.twig', [
            "form" => $form->createView()
        ]);
    }

    /**
     * Link to this controller to start the "connect" process
     *
     * @Route("/backoffice/settings", name="backoffice_settings")
     * @Iconize("fas fa-fw fa-tools")
     */
    public function Settings(Request $request, array $fields = []): Response
    {
        $fields = array_reverse(array_merge(array_reverse([
            "base.settings.logo"                                => ["translatable" => true, "multiple" => false, "form_type" => ImageType::class],
            "base.settings.logo.backoffice"                     => ["form_type" => ImageType::class, "multiple" => false, "required" => false],
            "base.settings.title"                               => ["translatable" => true],
            "base.settings.title.backoffice"                    => ["translatable" => true, "required" => false],
            "base.settings.meta.author"                         => ["translatable" => true, "required" => false],
            "base.settings.meta.description"                    => ["form_type" => TextareaType::class, "translatable" => true, "required" => false],
            "base.settings.meta.keywords"                       => ["form_type" => SelectType::class, "required" => false, "tags" => true, 'tokenSeparators' => [',', ';'], "multiple" => true, "translatable" => true],
            "base.settings.slogan"                              => ["translatable" => true, "required" => false],
            "base.settings.birthdate"                           => ["form_type" => DateTimePickerType::class],
            "base.settings.access_restriction.redirect_on_deny" => ["roles" => "ROLE_EDITOR", "form_type" => RouteType::class, "required" => false],
            "base.settings.access_restriction.anonymous_access" => ["roles" => "ROLE_SUPERADMIN" , "form_type" => BooleanType::class],
            "base.settings.access_restriction.user_access"      => ["roles" => "ROLE_SUPERADMIN" , "form_type" => BooleanType::class],
            "base.settings.access_restriction.admin_access"     => ["roles" => "ROLE_EDITOR", "form_type" => BooleanType::class],
            "base.settings.maintenance"                         => ["form_type" => BooleanType::class],
            "base.settings.maintenance.downtime"                => ["form_type" => DateTimePickerType::class, "required" => false],
            "base.settings.maintenance.uptime"                  => ["form_type" => DateTimePickerType::class, "required" => false],
            "base.settings.mail"                                => ["form_type" => EmailType::class],
            "base.settings.mail.name"                           => ["translatable" => true],
        ]), array_reverse($fields)));

        foreach($fields as $name => &$options) {

            $roles = array_pop_key("roles", $options);
            if($roles && !$this->getUser()->isGranted($roles)) unset($fields[$name]);
        }

        $form = $this->createForm(SettingListType::class, null, ["fields" => $fields]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){

            $data     = array_filter($form->getData(), fn($value) => !is_null($value));
            $fields   = array_keys($form->getConfig()->getOption("fields"));
            $settings = array_transforms(
                fn($k,$s): ?array => $s === null ? null : [$s->getPath(), $s] ,
                $this->settingBag->getRawScalar($fields, false)
            );

            foreach(array_diff_key($data, $settings) as $name => $setting)
                $this->settingRepository->persist($setting);

            $notification = new Notification("@controllers.backoffice_settings.success");
            $notification->setUser($this->getUser());
            $notification->send("success");

            $this->settingRepository->flush();
            $this->settingBag->clearAll();

            return $this->router->reloadRequest();
        }

        $this->settingBag->clearAll();
        return $this->render('backoffice/settings.html.twig', [
            "form" => $form->createView()
        ]);
    }

    /**
     * Link to this controller to start the "connect" process
     *
     * @Route("/backoffice/widgets", name="backoffice_widgets")
     * @Iconize("fas fa-fw fa-th-large")
     */
    public function Widgets(Request $request, array $widgetSlots = []): Response
    {
        $data = $this->widgetRepository->findAll();

        $form = $this->createForm(WidgetListType::class, $data, ["widgets" => $widgetSlots]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){

            $data = $form->getData();
            foreach(array_keys($widgetSlots) as $path) {

                $widgetSlot = $this->slotRepository->findOneByPath($path);

                if(!$widgetSlot) {
                    $widgetSlot = new Slot($path);
                    $this->slotRepository->persist($widgetSlot);
                    $this->slotRepository->flush();
                }

                $widget = $data[$path] ?? null;
                if(is_array($widget)) $widget = first($widget);
                $widgetSlot->setWidget($widget);
            }


            $notification = new Notification("@controllers.backoffice_widgets.success");
            $notification->setUser($this->getUser());
            $notification->send("success");

            return $this->router->reloadRequest();
        }

        return $this->render('backoffice/widgets.html.twig', ["form" => $form->createView()]);
    }

    public function configureExtension(Extension $extension) : Extension
    {
        return $extension;
    }

    public function configureDashboard(): Dashboard
    {
        $logo  = $this->settingBag->getScalar("base.settings.logo.backoffice");
        if(!$logo) $logo = $this->settingBag->getScalar("base.settings.logo");
        if(!$logo) $logo = "bundles/base/logo.svg";

        $title  = $this->settingBag->getScalar("base.settings.title")  ?? $this->translator->trans("backoffice.title", [], self::TRANSLATION_DASHBOARD);
        $slogan = $this->settingBag->getScalar("base.settings.slogan") ?? $this->translator->trans("backoffice.slogna", [], self::TRANSLATION_DASHBOARD);

        $this->configureExtension($this->extension
            ->setIcon("fas fa-laptop-house")
            ->setTitle($title)
            ->setText($slogan)
            ->setLogo($logo)
            ->setWidgets($this->configureWidgetItems())
        );

        $logo = $this->twig->getAsset($logo);
        $logo = $this->imageService->thumbnail($logo, 500, 500);

        return parent::configureDashboard()
            ->setFaviconPath("favicon.ico")
            ->setTranslationDomain(self::TRANSLATION_DASHBOARD)
            ->setTitle($title);
    }

    public function addRoles(array &$menu, string $class)
    {
        foreach ($class::getPermittedValuesByGroup(false) as $values) {

            if ($values == UserRole::USER) continue;

            if(!is_array($values)) $values = ["_self" => $values];
            $role = array_pop_key("_self", $values);

            $label = $this->translator->enum($class, $role, Translator::NOUN_PLURAL);
            $icon  = UserRole::getIcon($role, 1) ?? "fas fa-fw fa-user";

            $url = $this->adminUrlGenerator
                ->unsetAll()
                ->setController(UserCrudController::class)
                ->setAction(Action::INDEX)
                ->set("role", $role)
                ->set("filters[roles][comparison]", "like")
                ->set("filters[roles][value]", $role)
                ->set(EA::MENU_INDEX, count($menu))
                ->generateUrl();

            if(empty($values)) $item = MenuItem::linkToUrl($label, $icon, $url);
            else {

                $item = MenuItem::subMenu($label, $icon, $url);

                $subItems = [];
                foreach($values as $role)  {

                    $label = mb_ucfirst($this->translator->enum($role, $class, Translator::NOUN_PLURAL));
                    $icon  = UserRole::getIcon($role, 1) ?? "fas fa-fw fa-user";

                    $url = $this->adminUrlGenerator
                        ->unsetAll()
                        ->setController(UserCrudController::class)
                        ->setAction(Action::INDEX)
                        ->set("role", $role)
                        ->set("filters[roles][comparison]", "like")
                        ->set("filters[roles][value]", $role)
                        ->set(EA::MENU_INDEX, count($menu))
                        ->set(EA::SUBMENU_INDEX, count($subItems))
                        ->generateUrl();

                    $subItems[] = MenuItem::linkToUrl($label, $icon, $url);
                }

                $item->setSubItems($subItems);
            }

            $menu[] = $item;
        }

        return $menu;
    }

    public function configureMenuItems(): iterable
    {
        $menu   = [];
        $menu[] = MenuItem::section();
        $menu[] = MenuItem::linkToRoute("backoffice", [], "Home");
        $menu[] = MenuItem::linkToRoute("backoffice_apikey");
        $menu[] = MenuItem::linkToRoute("backoffice_settings");
        $menu[] = MenuItem::linkToRoute("backoffice_widgets");
        $menu[] = MenuItem::linkToRoute("app_index", [], 'Back to website', 'fas fa-fw fa-door-open');

        $menu[] = MenuItem::section('BUSINESS CARD');
        if(UserRole::class != \Base\Enum\UserRole::class)
            $menu   = $this->addRoles($menu, UserRole::class);

        if ($this->isGranted('ROLE_EDITOR')) {

            $menu[] = MenuItem::section('MEMBERSHIP');

            $menu   = $this->addRoles($menu, \Base\Enum\UserRole::class);
            $menu[] = MenuItem::linkToCrud(User::class, "All users", 'fas fa-fw fa-tags', );
            $menu[] = MenuItem::linkToCrud(User::class, 'Add user', 'fas fa-fw fa-plus-circle')->setPermission('ROLE_EDITOR')
                ->setAction('new');
        }

        return $menu;
    }

    public function configureActions(): Actions
    {
        return Actions::new($this->adminUrlGenerator, $this->entityManager)

            ->add(Crud::PAGE_INDEX, Action::NEW, 'fas fa-fw fa-edit')
            ->add(Crud::PAGE_INDEX, Action::EDIT, 'fas fa-fw fa-pencil-alt', fn(EaAction $a) => $a->setLabel(""))
            ->add(Crud::PAGE_INDEX, Action::DETAIL, 'fas fa-fw fa-search', fn(EaAction $a) => $a->setLabel(""))
            ->add(Crud::PAGE_INDEX, Action::DELETE, 'fas fa-fw fa-trash-alt', fn(EaAction $a) => $a->setLabel(""))

            ->add(Crud::PAGE_DETAIL, Action::GOTO_NEXT, 'fas fa-fw fa-angle-right')
            ->add(Crud::PAGE_DETAIL, Action::INDEX, 'fas fa-fw fa-undo')
            ->add(Crud::PAGE_DETAIL, Action::GOTO_PREV, 'fas fa-fw fa-angle-left')
            ->add(Crud::PAGE_DETAIL, Action::EDIT, 'fas fa-fw fa-pencil-alt')
            ->add(Crud::PAGE_DETAIL, Action::DELETE, 'fas fa-fw fa-trash-alt', fn(EaAction $a) => $a->setLabel(""))

            ->add(Crud::PAGE_EDIT, Action::INDEX, 'fas fa-fw fa-undo')
            ->add(Crud::PAGE_EDIT, Action::DETAIL, 'fas fa-fw fa-search')
            ->add(Crud::PAGE_EDIT, Action::DELETE, 'fas fa-fw fa-trash-alt', fn(EaAction $a) => $a->setLabel(""))
            ->add(Crud::PAGE_EDIT, Action::SEPARATOR)
            ->add(Crud::PAGE_EDIT, Action::GOTO_NEXT, 'fas fa-fw fa-angle-right')
            ->add(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, 'fas fa-fw fa-save')
            ->add(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE, 'fas fa-fw fa-edit')
            ->add(Crud::PAGE_EDIT, Action::GOTO_PREV, 'fas fa-fw fa-angle-left')

            ->add(Crud::PAGE_NEW, Action::INDEX, 'fas fa-fw fa-backspace')
            ->add(Crud::PAGE_NEW, Action::SAVE_AND_RETURN, 'fas fa-fw fa-edit')
            ->add(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER, 'fas fa-fw fa-edit');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Usually it's better to call the parent method because that gives you a
        // user menu with some menu items already created ("sign out", "exit impersonation", etc.)
        // if you prefer to create the user menu from scratch, use: return UserMenu::new()->...
        $avatar = ($user->getAvatarFile() ? $user->getAvatar() : null);
        $avatar = $this->imageService->thumbnail($avatar, 200, 200);

        return parent::configureUserMenu($user)
            ->setAvatarUrl($avatar)
            ->addMenuItems([
                MenuItem::linkToRoute("user_profile"),
                MenuItem::linkToRoute("user_settings")
            ])->setAvatarUrl($avatar);
    }

    public function configureWidgetItems(array $widgets = []) : array
    {
        WidgetItem::setAdminUrlGenerator($this->adminUrlGenerator);
        WidgetItem::setAdminContextProvider($this->adminContextProvider);
        $widgets = $this->addSectionWidgetItem($widgets, WidgetItem::section('LAYOUT', null, 1));
        if ($this->isGranted('ROLE_EDITOR')) {

            $section = $this->getSectionWidgetItem($widgets, "LAYOUT");
            if($section) $section->setWidth(2);

            $widgets = $this->addWidgetItem($widgets, "LAYOUT", [
                WidgetItem::linkToCrud(Setting::class),
                WidgetItem::linkToCrud(Slot::class),
                WidgetItem::linkToCrud(Attachment::class),
                WidgetItem::linkToCrud(Link::class),
            ]);
        }

        $widgets = $this->addWidgetItem($widgets, "LAYOUT", [
            WidgetItem::linkToCrud(Short::class),
            WidgetItem::linkToCrud(Menu::class),
            WidgetItem::linkToCrud(Page::class),
            WidgetItem::linkToCrud(Widget::class),
            WidgetItem::linkToCrud(Image::class),
            WidgetItem::linkToCrud(AbstractAttribute::class),
        ]);

        if ($this->isGranted('ROLE_EDITOR')) {

            $widgets = $this->addSectionWidgetItem($widgets, WidgetItem::section('THREADS', null, 1));
            $widgets = $this->addWidgetItem($widgets, "THREADS", [
                WidgetItem::linkToCrud(Thread::class),
                WidgetItem::linkToCrud(Mention::class),
                WidgetItem::linkToCrud(Tag::class),
                WidgetItem::linkToCrud(Taxon::class),
                WidgetItem::linkToCrud(Like::class),
            ]);

            $widgets = $this->addSectionWidgetItem($widgets, WidgetItem::section('EXTENSIONS', null, 1));
            $widgets = $this->addWidgetItem($widgets, "EXTENSIONS", [
                WidgetItem::linkToCrud(Log::class),
                WidgetItem::linkToCrud(Ordering::class),
                WidgetItem::linkToCrud(Revision::class),
                WidgetItem::linkToCrud(TrashBall::class),
            ]);

            $widgets = $this->addSectionWidgetItem($widgets, WidgetItem::section('MEMBERSHIP', null, 2));
            $widgets = $this->addWidgetItem($widgets, "MEMBERSHIP", [
                WidgetItem::linkToCrud(User::class),
                WidgetItem::linkToCrud(UserGroup::class),
                WidgetItem::linkToCrud(UserNotification::class),
                WidgetItem::linkToCrud(UserPermission::class),
                WidgetItem::linkToCrud(UserPenalty::class),
                WidgetItem::linkToCrud(UserToken::class),
            ]);

        }

        return $widgets;
    }
}
