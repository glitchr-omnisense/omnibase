<?php

namespace Base\Controller\Dashboard;

use App\Entity\User              as User;
use App\Entity\User\Group        as UserGroup;
use App\Entity\User\Log          as UserLog;
use App\Entity\User\Token        as UserToken;
use App\Entity\User\Notification as UserNotification;
use App\Entity\User\Permission   as UserPermission;
use App\Entity\User\Penalty      as UserPenalty;

use App\Controller\Dashboard\Crud\User\UserCrudController;
use Base\Service\BaseService;
use Base\Field\Type\RoleType;

use Base\Config\WidgetItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Google\Analytics\Service\GaService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class DashboardController extends AbstractDashboardController
{
    protected $baseService;
    protected $adminUrlGenerator;
    protected $authenticationUtils;

    public function __construct(
        AdminUrlGenerator $adminUrlGenerator,
        AdminContextProvider $adminContextProvider,
        BaseService $baseService,
        AuthenticationUtils $authenticationUtils,
        ?GaService $gaService = null
    ) {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->adminContextProvider  = $adminContextProvider;

        $this->baseService       = $baseService;
        $this->authenticationUtils       = $authenticationUtils;
        $this->gaService = $gaService;
    }

   /**
     * @Route("/dashboard/google/analytics", name="base_dashboard_ga")
     */
    public function GoogleAnalytics(): Response
    {
        return $this->render('dashboard/google/analytics.html.twig', [
            "ga" => $this->gaService->getBasics()
        ]);
    }

    /**
     * Link to this controller to start the "connect" process
     *
     * @Route("/dashboard", name="base_dashboard")
     */
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig', [
            "content_title" => "Dashboard: home page",
            "content_header" => "Welcome to this administration page."
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        $logo = $this->baseService->getParameterBag("base.logo");
        return Dashboard::new()
            ->setTitle('<img src="'.$logo.'" alt="Dashboard">')
            ->disableUrlSignatures();
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setPaginatorPageSize(30);
    }

    public function configureMenuItems(): iterable
    {
        $menu   = [];
        $menu[] = MenuItem::section('MENU');
        $menu[] = MenuItem::linktoUrl('Home', 'fa fa-home', $this->baseService->getRoute("base_dashboard"));
        $menu[] = MenuItem::linktoUrl('Back to website', 'fa fa-door-open', "/");

        $menu[] = MenuItem::section('MEMBERSHIP');
        $roles = RoleType::array_flatten(RoleType::getChoices());

        foreach ($roles as $label => $role) {

            if ($role == "ROLE_USER") continue;
            $value = $label;
            $icon  = RoleType::getAltIcons()[$role] ?? "fa fa-fw";

            $url = $this->adminUrlGenerator
                ->unsetAll()
                ->setController(UserCrudController::class)
                ->setAction(Action::INDEX)
                ->set("role", $role)
                ->set("filters[roles][comparison]", "like")
                ->set("filters[roles][value]", $role)
                ->set("menuIndex", count($menu))
                ->generateUrl();

            $menu[] = MenuItem::linkToUrl($value, $icon, $url);
        }

        $menu[] = MenuItem::linkToCrud('All users', 'fa-fw fa fa-tags', User::class);
        $menu[] = MenuItem::linkToCrud('Add user', 'fa-fw fa fa-plus-circle', User::class)->setPermission('ROLE_SUPERADMIN')
            ->setAction('new');



        if (isset($this->gaService) && $this->gaService->isEnabled()) {

            $ga = $this->gaService->getBasics();

            $gaMenu = [];
            $gaMenu["users"]        = ["label" => $ga["users"] . " visit(s)", "icon"  => 'fas fa-user'];
            $gaMenu["users_1day"]   = ["label" => $ga["users_1day"] . " visit(s) in one day", "icon"  => 'fas fa-user-clock'];
            $gaMenu["views"]        = ["label" => $ga["views"] . " visit(s)", "icon"  => 'far fa-eye'];
            $gaMenu["views_1day"]   = ["label" => $ga["views_1day"] . " visit(s) in one day", "icon"  => 'fas fa-eye'];
            $gaMenu["sessions"]     = ["label" => $ga["sessions"] . " sessions(s)", "icon"  => 'fas fa-stopwatch'];
            $gaMenu["bounces_1day"] = ["label" => $ga["bounces_1day"] . " bounce(s) in one day", "icon"  => 'fas fa-meteor'];

            $menu[] = MenuItem::section('STATISTICS');
            foreach ($gaMenu as $key => $entry) {

                $url = $this->adminUrlGenerator
                    ->unsetAll()
                    ->setRoute("app_dashboard_ga")
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
            ->update(Crud::PAGE_INDEX, Action::EDIT,
                fn (Action $action) => $action->setIcon('fa fa-fw fa-pencil-alt'))
            ->update(Crud::PAGE_INDEX, Action::DELETE,
                fn (Action $action) => $action->setIcon('fa fa-fw fa-trash-alt'))

            ->update(Crud::PAGE_DETAIL, Action::EDIT,
                fn (Action $action) => $action->setIcon('fa fa-fw fa-pencil-alt'))
            ->update(Crud::PAGE_DETAIL, Action::INDEX,
                fn (Action $action) => $action->setIcon('fa fa-fw fa-home'))
            ->update(Crud::PAGE_DETAIL, Action::DELETE,
                fn (Action $action) => $action->setIcon('fa fa-fw fa-trash-alt'))

            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN,
                fn (Action $action) => $action->setIcon('fa fa-fw fa-save'))
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE,
                fn (Action $action) => $action->setIcon('fa fa-fw fa-edit'))

            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN,
                fn (Action $action) => $action->setIcon('fa fa-fw fa-edit'))
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER,
                fn (Action $action) => $action->setIcon('fa fa-fw fa-edit'));

    }

    public function configureWidgetItems($widget = [])
    {
        WidgetItem::setAdminUrlGenerator($this->adminUrlGenerator);
        WidgetItem::setAdminContextProvider($this->adminContextProvider);

        $widget[] = WidgetItem::section('MEMBERSHIP', null, 2);
        $widget[] = WidgetItem::linkToCrud('Users',         'fa-fw fa fa-user',                 User::class);
        $widget[] = WidgetItem::linkToCrud('Groups',        'fa-fw fa fa-users',                UserGroup::class);
        $widget[] = WidgetItem::linkToCrud('Notifications', 'fa-fw fa fa-bell',                 UserNotification::class);
        $widget[] = WidgetItem::linkToCrud('Tokens',        'fa-fw fa fa-drumstick-bite',       UserToken::class);
        $widget[] = WidgetItem::linkToCrud('Permissions',   'fa-fw fa fa-exclamation-triangle', UserPermission::class);
        $widget[] = WidgetItem::linkToCrud('Penalties',     'fa-fw fa fa-bomb',                 UserPenalty::class);
        $widget[] = WidgetItem::linkToCrud('Logs',          'fa-fw fa fa-info-circle',          UserLog::class);


        if ($this->gaService->isEnabled()) {

            $ga = $this->gaService->getBasics();

            $menu[] = MenuItem::section('STATISTICS');
            $menu[] = MenuItem::linkToUrl($ga["users"] . ' visit(s)', 'fas fa-user', "");
            $menu[] = MenuItem::linkToUrl($ga["users_1day"] . ' visit(s) in one day', 'fas fa-user-clock', "");
            $menu[] = MenuItem::linkToUrl($ga["views"] . ' view(s)', 'far fa-eye', "");
            $menu[] = MenuItem::linkToUrl($ga["views_1day"] . ' view(s) in one day', 'fas fa-eye', "");
            $menu[] = MenuItem::linkToUrl($ga["sessions"] . ' sessions(s)', 'fas fa-stopwatch', "");
            $menu[] = MenuItem::linkToUrl($ga["bounces_1day"] . ' bounce(s) in one day', 'fas fa-meteor', "");
        }

        return $widget;
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Usually it's better to call the parent method because that gives you a
        // user menu with some menu items already created ("sign out", "exit impersonation", etc.)
        // if you prefer to create the user menu from scratch, use: return UserMenu::new()->...
        return parent::configureUserMenu($user)

            // you can use any type of menu item, except submenus
            ->addMenuItems([
                MenuItem::linkToUrl('My Profile', 'fa fa-id-card', $this->baseService->getRoute("base_profile")),
                MenuItem::linkToUrl('My Settings', 'fa fa-user-cog', $this->baseService->getRoute("base_settings"))
            ]);
    }
}
