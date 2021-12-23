<?php

namespace Base\Controller\Dashboard;

use App\Entity\User              as User;
use App\Entity\User\Group        as UserGroup;
use App\Entity\User\Log          as UserLog;
use App\Entity\User\Token        as UserToken;
use App\Entity\User\Notification as UserNotification;
use App\Entity\User\Permission   as UserPermission;
use App\Entity\User\Penalty      as UserPenalty;

use App\Entity\Thread;
use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;
use App\Entity\Thread\Tag;
use App\Entity\Sitemap\Widget\Slot;
use App\Entity\Sitemap\Attribute\HyperpatternAttribute as Hyperpattern;

use App\Entity\Sitemap\Setting;
use App\Entity\Sitemap\Widget;
use App\Entity\User\Notification;
use App\Entity\Sitemap\Widget\Attachment;
use App\Entity\Sitemap\Widget\Hyperlink;
use App\Entity\Sitemap\Widget\Menu;
use App\Entity\Sitemap\Widget\Page;

use App\Controller\Dashboard\Crud\UserCrudController;
use Base\Config\WidgetItem;
use Base\Service\BaseService;
use Base\Field\Type\RoleType;

use App\Enum\UserRole;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\ImageType;
use Base\Form\Type\Sitemap\SettingListType;
use Base\Form\Type\Sitemap\WidgetListType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use Base\Config\Extension;
use Base\Entity\Sitemap\Attribute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Google\Analytics\Service\GaService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Request;

use Base\Traits\DashboardWidgetTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/* "abstract" (remove because of routes) */
class AbstractDashboardController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController
{
    use DashboardWidgetTrait;

    protected $baseService;
    protected $adminUrlGenerator;

    public const TRANSLATION_DASHBOARD = "dashboard";
    public const TRANSLATION_ENTITY    = "entities";
    public const TRANSLATION_ENUM      = "enums";

    public function __construct(
        Extension $extension,
        RequestStack $requestStack,
        TranslatorInterface $translator,
        AdminContextProvider $adminContextProvider,
        AdminUrlGenerator $adminUrlGenerator,
        BaseService $baseService,
        ?GaService $gaService = null) {

        $this->extension = $extension;
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->adminContextProvider  = $adminContextProvider;

        $this->translator = $translator;
        WidgetItem::$translator = $translator;

        $this->baseService           = $baseService;
        $this->settingRepository     = $baseService->getEntityManager()->getRepository(Setting::class);
        $this->widgetRepository      = $baseService->getEntityManager()->getRepository(Widget::class);
        $this->slotRepository  = $baseService->getEntityManager()->getRepository(Slot::class);

        $this->gaService = $gaService;
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
     * @Route("/dashboard", name="base_dashboard")
     */
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig');
    }

    /**
     * Link to this controller to start the "connect" process
     *
     * @Route("/dashboard/ga", name="base_dashboard_ga")
     */
    public function GoogleAnalytics(): Response
    {
        return $this->render('dashboard/index.html.twig');
    }

    /**
     * Link to this controller to start the "connect" process
     *
     * @Route("/dashboard/settings", name="base_dashboard_settings")
     */
    public function Settings(Request $request, array $fields = []): Response
    {
        $fields = array_merge([
            "base.settings.logo"                 => ["form_type" => ImageType::class],
            "base.settings.logo.backoffice"      => ["form_type" => ImageType::class],
            "base.settings.title"                => [],
            "base.settings.slogan"               => [],
            "base.settings.birthdate"            => ["form_type" => DateTimePickerType::class],
            "base.settings.maintenance"          => ["form_type" => CheckboxType::class, "required" => false],
            "base.settings.maintenance.downtime" => ["form_type" => DateTimePickerType::class, "required" => false],
            "base.settings.maintenance.uptime"   => ["form_type" => DateTimePickerType::class, "required" => false],
            "base.settings.domain.https"         => ["form_type" => HiddenType::class, "data" => mb_strtolower($_SERVER['REQUEST_SCHEME'] ?? $_SERVER["HTTPS"] ?? "https") == "https"],
            "base.settings.domain"               => ["form_type" => HiddenType::class, "data" => mb_strtolower($_SERVER['HTTP_HOST'])],
            "base.settings.domain.base_dir"      => ["form_type" => HiddenType::class, "data" => $this->baseService->getAsset("/")],
            "base.settings.mail"                 => ["form_type" => EmailType::class],
            "base.settings.mail.name"            => [],
        ], $fields);

        $singleLocale = array_keys($fields);
        $singleLocale = array_filter($singleLocale, fn($v) => $v != "base.settings.logo");
        $singleLocale = array_filter($singleLocale, fn($v) => $v != "base.settings.title");
        $singleLocale = array_filter($singleLocale, fn($v) => $v != "base.settings.slogan");
        $singleLocale = array_filter($singleLocale, fn($v) => $v != "base.settings.mail.name");
        
        $form = $this->createForm(SettingListType::class, null, [
            "fields" => $fields,
            "fields[single_locale]" => $singleLocale
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){

            $data     = array_filter($form->getData(), fn($value) => !is_null($value));
            $fields   = array_keys($form->getConfig()->getOption("fields"));

            $settings = array_key_transforms(function($k, $f): ?array {

                $setting = $f["_self"] ?? null;
                if($setting === null) return null;

                return [$setting->getName(), $f["_self"]];

            }, $this->baseService->getSettings()->getRaw($fields));
            $settings = array_filter($settings, fn($value) => !is_null($value));
            
            dump($data);

            foreach(array_diff_key($data, $settings) as $name => $setting)
                $this->settingRepository->persist($setting);

            $this->settingRepository->flush();

            $notification = new Notification("@dashboard.controllers.settings.success");
            $notification->setUser($this->getUser());
            $notification->send("success");

            return $this->baseService->refresh();
        }

        return $this->render('dashboard/settings.html.twig', [
            "form" => $form->createView()
        ]);
    }

    /**
     * Link to this controller to start the "connect" process
     *
     * @Route("/dashboard/widgets", name="base_dashboard_widgets")
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
                }

                $widgets = $data[$path] ?? [];
                $widgetSlot->setWidgets($widgets);
            }

            $this->slotRepository->flush();

            $notification = new Notification("@dashboard.controllers.widgets.success");
            $notification->setUser($this->getUser());
            $notification->send("success");

            return $this->baseService->refresh();
        }

        return $this->render('dashboard/widgets.html.twig', ["form" => $form->createView()]);
    }

    public function configureExtension(Extension $extension) : Extension
    {
        return $extension;
    }

    public function configureDashboard(): Dashboard
    {
        $logo  = $this->baseService->getSettings()->getScalar("base.settings.logo.backoffice");
        if(!$logo) $logo = $this->baseService->getSettings()->getScalar("base.settings.logo");
        if(!$logo) $logo = "bundles/base/logo.svg";

        $title = $this->baseService->getSettings()->getScalar("base.settings.title") ?? "";
        $slogan = $this->baseService->getSettings()->getScalar("base.settings.slogan") ?? "";

        $this->configureExtension($this->extension
            ->setIcon("fas fa-laptop-house")
            ->setTitle($title)
            ->setText($slogan)
            ->setWidgets($this->configureWidgetItems())
        );

        return parent::configureDashboard()
            ->setTranslationDomain(self::TRANSLATION_DASHBOARD)
            ->setTitle('<img src="'.$this->baseService->getAsset($logo).'" alt="'.$title.'">')
            ->disableUrlSignatures();
    }

    public function addRoles(array &$menu, array $roles)
    {
        foreach ($roles as $i => $role) {

            if ($role == UserRole::USER) continue;

            $label = mb_ucfirst($this->translator->trans("user_role.".mb_strtolower($role).".plural", [], self::TRANSLATION_ENUM));
            $icon  = UserRole::getIcons(1)[$role] ?? "fas fa-fw";

            $url = $this->adminUrlGenerator
                ->unsetAll()
                ->setController(UserCrudController::class)
                ->setAction(Action::INDEX)
                ->set("role", $role)
                ->set("filters[roles][comparison]", "like")
                ->set("filters[roles][value]", $role)
                ->set("menuIndex", count($menu))
                ->generateUrl();

            $menu[] = MenuItem::linkToUrl($label, $icon, $url);
        }

        return $menu;
    }

    public function configureMenuItems(): iterable
    {
        $menu   = [];
        $menu[] = MenuItem::section(false);
        $menu[] = MenuItem::linkToUrl('Home', 'fas fa-fw fa-home', $this->baseService->getUrl("base_dashboard"));
        $menu[] = MenuItem::linkToUrl('Settings', 'fas fa-fw fa-tools', $this->baseService->getUrl("base_dashboard_settings"));
        $menu[] = MenuItem::linkToUrl('Widgets', 'fas fa-fw fa-th-large', $this->baseService->getUrl("base_dashboard_widgets"));
        $menu[] = MenuItem::linkToUrl('Back to website', 'fas fa-fw fa-door-open', $this->baseService->getAsset("/"));

        $menu[] = MenuItem::section('BUSINESS CARD');
        if(UserRole::class != \Base\Enum\UserRole::class)
            $menu   = $this->addRoles($menu, array_diff(UserRole::getPermittedValues(), \Base\Enum\UserRole::getPermittedValues()));

        if ($this->isGranted('ROLE_SUPERADMIN')) {

            $menu[] = MenuItem::section('MEMBERSHIP');
            $menu   = $this->addRoles($menu, \Base\Enum\UserRole::getPermittedValues());
            $menu[] = MenuItem::linkToCrud('All users', 'fas fa-fw fa-tags', User::class);
            $menu[] = MenuItem::linkToCrud('Add user', 'fas fa-fw fa-plus-circle', User::class)->setPermission('ROLE_SUPERADMIN')
                ->setAction('new');
        }
    
        if (isset($this->gaService) && $this->gaService->isEnabled()) {

            $ga = $this->gaService->getBasics();

            $gaMenu = [];
            $gaMenu["users"]        = ["label" => $this->translator->trans("users", [$ga["users"]], self::TRANSLATION_DASHBOARD), "icon"  => 'fas fa-user'];
            $gaMenu["users_1day"]   = ["label" => $this->translator->trans("users_1day", [$ga["users_1day"]], self::TRANSLATION_DASHBOARD), "icon"  => 'fas fa-user-clock'];
            $gaMenu["views"]        = ["label" => $this->translator->trans("views", [$ga["views"]], self::TRANSLATION_DASHBOARD), "icon"  => 'far fa-eye'];
            $gaMenu["views_1day"]   = ["label" => $this->translator->trans("views_1day", [$ga["views_1day"]], self::TRANSLATION_DASHBOARD) , "icon"  => 'fas fa-eye'];
            $gaMenu["sessions"]     = ["label" => $this->translator->trans("sessions", [$ga["sessions"]], self::TRANSLATION_DASHBOARD), "icon"  => 'fas fa-stopwatch'];
            $gaMenu["bounces_1day"] = ["label" => $this->translator->trans("bounces_1day", [$ga["bounces_1day"]], self::TRANSLATION_DASHBOARD), "icon"  => 'fas fa-meteor'];

            $menu[] = MenuItem::section('STATISTICS');
            foreach ($gaMenu as $key => $entry) {

                $url = $this->adminUrlGenerator
                    ->unsetAll()
                    ->setRoute("base_dashboard_ga")
                    ->set("menuIndex", count($menu))
                    ->set("show", $key)
                    ->generateUrl();

                $menu[] = MenuItem::linkToUrl(
                    $entry["label"], $entry["icon"], $url);
            }
        }

        return $menu;
    }

    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            
            ->update(Crud::PAGE_INDEX, Action::NEW,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-edit'))
            ->update(Crud::PAGE_INDEX, Action::DETAIL,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-search')->setLabel(""))
            ->update(Crud::PAGE_INDEX, Action::EDIT,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-pencil-alt')->setLabel(""))
            ->update(Crud::PAGE_INDEX, Action::DELETE,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-trash-alt')->setLabel(""))

            ->reorder(Crud::PAGE_DETAIL, [Action::INDEX, Action::DELETE,  Action::EDIT])
            ->update(Crud::PAGE_DETAIL, Action::INDEX,
            fn (Action $action) => $action->setIcon('fas fa-fw fa-undo'))
            ->update(Crud::PAGE_DETAIL, Action::EDIT,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-pencil-alt'))
            ->update(Crud::PAGE_DETAIL, Action::DELETE,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-trash-alt'))

            ->add(Crud::PAGE_EDIT, Action::INDEX) // Adding return button..
            ->update(Crud::PAGE_EDIT, Action::INDEX,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-undo'))
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-save'))
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-edit'))

            ->add(Crud::PAGE_NEW, Action::INDEX) // Adding return button..
            ->update(Crud::PAGE_NEW, Action::INDEX,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-backspace'))
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-edit'))
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER,
                fn (Action $action) => $action->setIcon('fas fa-fw fa-edit'));
    }
    
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Usually it's better to call the parent method because that gives you a
        // user menu with some menu items already created ("sign out", "exit impersonation", etc.)
        // if you prefer to create the user menu from scratch, use: return UserMenu::new()->...
        $avatar = ($user->getAvatarFile() ? $user->getAvatar() : null);

        return parent::configureUserMenu($user)
            ->setAvatarUrl($avatar)
            ->addMenuItems([
                MenuItem::linkToUrl('My Profile', 'fas fa-fw fa-id-card', $this->baseService->getUrl("base_profile")),
                MenuItem::linkToUrl('My Settings', 'fas fa-fw fa-user-cog', $this->baseService->getUrl("base_user_settings"))
            ])->setAvatarUrl($avatar);
    }

    public function configureWidgetItems(array $widgets = []) : array
    {
        WidgetItem::setAdminUrlGenerator($this->adminUrlGenerator);
        WidgetItem::setAdminContextProvider($this->adminContextProvider);

        if ($this->isGranted('ROLE_SUPERADMIN')) {

            $widgets = $this->addSectionWidgetItem($widgets, WidgetItem::section('MEMBERSHIP', null, 2));
            $widgets = $this->addWidgetItem($widgets, "MEMBERSHIP", [
                WidgetItem::linkToCrud(User::class),
                WidgetItem::linkToCrud(UserGroup::class),
                WidgetItem::linkToCrud(UserNotification::class),
                WidgetItem::linkToCrud(UserToken::class),
                WidgetItem::linkToCrud(UserPermission::class),
                WidgetItem::linkToCrud(UserPenalty::class),
                WidgetItem::linkToCrud(UserLog::class)
            ]);

            $widgets = $this->addSectionWidgetItem($widgets, WidgetItem::section('THREADS', null, 1));
            $widgets = $this->addWidgetItem($widgets, "THREADS", [
                WidgetItem::linkToCrud(Thread::class),
                WidgetItem::linkToCrud(Mention::class),
                WidgetItem::linkToCrud(Tag::class),
                WidgetItem::linkToCrud(Like::class),
            ]);
        }

        $widgets = $this->addSectionWidgetItem($widgets, WidgetItem::section('SITEMAP', null, 1));
        if ($this->isGranted('ROLE_SUPERADMIN')) {

            $section = $this->getSectionWidgetItem($widgets, "SITEMAP");
            if($section) $section->setWidth(2);

            $widgets = $this->addWidgetItem($widgets, "SITEMAP", [
                WidgetItem::linkToCrud(Setting::class  ),
                WidgetItem::linkToCrud(Widget::class   ),
                WidgetItem::linkToCrud(Slot::class     ),
                WidgetItem::linkToCrud(Attribute::class),
            ]);
        }

        $widgets = $this->addWidgetItem($widgets, "SITEMAP", [
            WidgetItem::linkToCrud(Menu::class      ),
            WidgetItem::linkToCrud(Attachment::class),
            WidgetItem::linkToCrud(Page::class      ),
            WidgetItem::linkToCrud(Hyperlink::class ),
        ]);

        return $widgets;
    }
}
