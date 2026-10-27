---
title: Routes and access
order: 60
---

# Routes and access

## Importing the routes

The core's controllers carry their routes as attributes. A site imports them
with the `BaseBundle` alias, beside its own:

```yaml
# config/routes.yaml
controllers:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
    type: attribute

base_controller:
    resource: "@BaseBundle/Controller"
    type: attribute
    prefix: /
```

`Base\Attributes\Attribute\Route` extends Symfony's `#[Route]`; it reads the
localized paths of `config/routes_paths.yaml`. A route with paths per language
is several routes, `<name>.<lang>` (`thread_search.en` is `/search`,
`thread_search.fr` is `/rechercher`). To have the route cache rebuilt when
that file changes alone, import it with the loader `base_route_paths`
(`Base\Routing\Loader\RouteTranslationsResourceLoader`, which registers the file
as a resource and declares no route).

The actions of `src/Controller/Action/` are plain classes whose `#[Route]` the
same import reads; what they return (a `View`, a `Template`, `Json`...) is
answered by the `ScreenResponder` (`kernel.view`, see [Events](events.md)).

## The core's routes

`bin/console debug:router` in the core's harness, the routes whose controller is
the core's (`Base\Controller\`, `Base\Bridge\`), the plugins' left out. `ANY`:
no method restriction. The short links answer on the subdomain `s` only
(`subdomain: "s"`, priority 1). `security_loginDemo` (`POST /login/demo`) exists in
the `demo` environment only ([The demo environment](../20-architecture/demo.md)).

### The sign-in, the account and its security

| Route | Path | Methods | Controller |
|---|---|---|---|
| `qr_code_totp` | `/members/qr/totp` | ANY | `Client\UserSettingsController::displayTotpQrCode` |
| `security_rescue` | `/rescue` | ANY | `RescueController::LoginRescue` |
| `security_login` | `/login` | ANY | `SecurityController::Login` |
| `security_logout` | `/logout` | ANY | `SecurityController::Logout` |
| `security_logoutRequest` | `/logout-request` | ANY | `SecurityController::LogoutRequest` |
| `security_loginWithToken` | `/login/token/{token}` | ANY | `SecurityController::LoginTokenRequest` |
| `security_register` | `/register` | ANY | `SecurityController::Register` |
| `security_verifyEmail` | `/verify-email` | ANY | `SecurityController::VerifyEmailRequest` |
| `security_verifyEmailWithToken` | `/verify-email/{token}` | ANY | `SecurityController::VerifyEmailResponse` |
| `security_adminApproval` | `/admin-approval` | ANY | `SecurityController::AdminApprovalRequest` |
| `security_accountGoodbye` | `/account-goodbye` | ANY | `SecurityController::DisableAccountRequest` |
| `security_accountWelcomeBackWithToken` | `/welcome-back/{token}` | ANY | `SecurityController::EnableAccountRequest` |
| `security_resetPassword` | `/reset-password` | ANY | `SecurityController::ResetPasswordRequest` |
| `security_resetPasswordWithToken` | `/reset-password/{token}` | ANY | `SecurityController::ResetPasswordResponse` |
| `security_changePassword` | `/change-password` | ANY | `SecurityController::ChangePassword` |
| `security_maintenance` | `/m` | ANY | `SecurityController::Maintenance` |
| `security_launch.fr` | `/est/bientot/en/ligne` | ANY | `SecurityController::Launch` |
| `security_launch.en` | `/is/coming/soon` | ANY | `SecurityController::Launch` |
| `security_pending.fr` | `/est/bientot/disponible` | ANY | `SecurityController::Pending` |
| `security_pending.en` | `/is/soon/available` | ANY | `SecurityController::Pending` |
| `security_pendingForApproval.fr` | `/en/attente/de/validation` | ANY | `SecurityController::PendingForApproval` |
| `security_pendingForApproval.en` | `/waiting/for/approval` | ANY | `SecurityController::PendingForApproval` |
| `admin_rescue` | `/rescue-request` | ANY | `RescueController::index` |

### The member's pages and settings

| Route | Path | Methods | Controller |
|---|---|---|---|
| `user_settings_2fa_email_toggle` | `/settings/2fa/email` | POST | `Action\Settings\EmailCodeToggle` |
| `user_settings_enrolment` | `/settings/security-required` | ANY | `Action\Settings\Enrolment` |
| `user_settings_enrolment_skip` | `/settings/security-required/skip` | POST | `Action\Settings\EnrolmentSkip` |
| `user_profileEdit` | `/profile/{id}/edit/` | ANY | `Client\UserProfileController::Edit` |
| `user_profile` | `/profile/{id}` | ANY | `Client\UserProfileController::Show` |
| `user_search` | `/search/user` | ANY | `Client\UserSearchController::Main` |
| `user_settings` | `/settings` | ANY | `Client\UserSettingsController::Settings` |
| `user_settings_passkey_rename` | `/settings/passkeys/{id}/rename` | POST | `Client\UserSettingsController::RenamePasskey` |
| `user_settings_passkey_delete` | `/settings/passkeys/{id}/delete` | POST | `Client\UserSettingsController::DeletePasskey` |
| `user_settings_session_logout` | `/settings/sessions/{id}/logout` | POST | `Client\UserSettingsController::LogoutSession` |
| `user_settings_2fa` | `/settings/2fa` | ANY | `Client\UserSettingsController::TwoFactorAuthentication` |
| `user_settings_2fa_disable` | `/settings/2fa/disable` | POST | `Client\UserSettingsController::TwoFactorAuthentication_Disable` |
| `user_settings_2fa_qrcode` | `/settings/2fa/qr-code` | ANY | `Client\UserSettingsController::TwoFactorAuthentication_QrCode` |

### Notifications and web push

| Route | Path | Methods | Controller |
|---|---|---|---|
| `user_notifications_delete` | `/notifications/delete` | POST | `Action\Notification\Delete` |
| `user_notifications_latest` | `/notifications/latest` | GET | `Action\Notification\Latest` |
| `user_notifications_read` | `/notifications/read` | POST | `Action\Notification\Read` |
| `user_notifications` | `/notifications` | ANY | `Client\NotificationController::Index` |
| `push_config` | `/push/config` | GET | `Client\NotificationController::PushConfig` |
| `push_subscribe` | `/push/subscribe` | POST | `Client\NotificationController::PushSubscribe` |
| `push_unsubscribe` | `/push/unsubscribe` | POST | `Client\NotificationController::PushUnsubscribe` |
| `push_service_worker` | `/sw.js` | GET | `Client\NotificationController::ServiceWorker` |

### Threads

| Route | Path | Methods | Controller |
|---|---|---|---|
| `api_thread_publish` | `/api/thread/{slug}/publish` | POST | `Api\ThreadController::Publish` |
| `api_thread_hide` | `/api/thread/{slug}/hide` | POST | `Api\ThreadController::Hide` |
| `api_thread_follow` | `/api/thread/{slug}/follow` | POST | `Api\ThreadController::Follow` |
| `api_thread_unfollow` | `/api/thread/{slug}/unfollow` | POST | `Api\ThreadController::Unfollow` |
| `api_thread_like` | `/api/thread/{slug}/like` | POST | `Api\ThreadController::Like` |
| `api_thread_unlike` | `/api/thread/{slug}/unlike` | POST | `Api\ThreadController::Unlike` |
| `thread_search.en` | `/search` | ANY | `Client\ThreadSearchController::Main` |
| `thread_search.fr` | `/rechercher` | ANY | `Client\ThreadSearchController::Main` |

### Media: images, files, uploads

| Route | Path | Methods | Controller |
|---|---|---|---|
| `ux_dropzone` | `/ux/dropzone/{data}` | ANY | `UX\DropzoneController::Main` |
| `ux_dropzone_preview` | `/ux/dropzone/{data}/{uuid}` | ANY | `UX\DropzoneController::Preview` |
| `ux_dropzone_delete` | `/ux/dropzone/{data}/{uuid}/delete` | ANY | `UX\DropzoneController::Delete` |
| `ux_serve` | `/contents/{data}` | ANY | `UX\MediaController::Serve` |
| `ux_imageCropExtension_cacheless` | `/images/cacheless/cropper/{identifier}/{data}/image.{extension}` | ANY | `UX\MediaController::ImageCropCacheless` |
| `ux_imageCrop_cacheless` | `/images/cacheless/cropper/{identifier}/{data}` | ANY | `UX\MediaController::ImageCropCacheless` |
| `ux_imageCropExtension` | `/images/cropper/{identifier}/{data}/image.{extension}` | ANY | `UX\MediaController::ImageCrop` |
| `ux_imageCrop` | `/images/cropper/{identifier}/{data}` | ANY | `UX\MediaController::ImageCrop` |
| `ux_imageWebp_cacheless` | `/images/cacheless/{data}/image.webp` | ANY | `UX\MediaController::ImageWebpCacheless` |
| `ux_debug_imageExtension` | `/images/debug/{data}/image.{extension}` | ANY | `UX\MediaController::ImageDebug` |
| `ux_debug_image` | `/images/debug/{data}` | ANY | `UX\MediaController::ImageDebug` |
| `ux_imageExtension_cacheless` | `/images/cacheless/{data}/image.{extension}` | ANY | `UX\MediaController::ImageCacheless` |
| `ux_image_cacheless` | `/images/cacheless/{data}` | ANY | `UX\MediaController::ImageCacheless` |
| `ux_imageWebp` | `/images/{data}/image.webp` | ANY | `UX\MediaController::ImageWebp` |
| `ux_imageSvg` | `/images/{data}/image.svg` | ANY | `UX\MediaController::ImageSvg` |
| `ux_imageExtension` | `/images/{data}/image.{extension}` | ANY | `UX\MediaController::Image` |
| `ux_image` | `/images/{data}` | ANY | `UX\MediaController::Image` |

### UX endpoints: autocompletion, validation, the editor

| Route | Path | Methods | Controller |
|---|---|---|---|
| `ux_editorjs_collabTicket` | `/ux/editorjs/collab-ticket` | POST | `UX\EditorController::CollabTicket` |
| `ux_editorjs_autosave` | `/ux/editorjs/autosave` | POST | `UX\EditorController::Autosave` |
| `ux_autocomplete` | `/ux/autocomplete/{data}` | ANY | `UX\AutocompleteController::Main` |
| `ux_autocomplete_forex` | `/ux/autocomplete/currency/{source}/{target}/{data}` | ANY | `UX\AutocompleteController::Forex` |
| `ux_autocomplete_icons` | `/ux/autocomplete/{provider}/{pageSize}/{data}` | ANY | `UX\AutocompleteController::Icons` |
| `ux_validation` | `/ux/validation/{hashid}` | ANY | `UX\AutovalidateController::Main` |
| `ux_editorjs_endpointByUser` | `/ux/editorjs/user/{data}/{page}` | ANY | `UX\EditorController::EndpointByUser` |
| `ux_editorjs_endpointByKeyword` | `/ux/editorjs/keyword/{data}/{page}` | ANY | `UX\EditorController::EndpointByKeyword` |
| `ux_editorjs_endpointByThread` | `/ux/editorjs/thread/{data}/{page}` | ANY | `UX\EditorController::EndpointByThread` |
| `ux_editorjs_uploadByFile` | `/ux/editorjs/{data}` | ANY | `UX\EditorController::UploadByFile` |
| `ux_editorjs_uploadByUrl` | `/ux/editorjs/{data}/fetch` | ANY | `UX\EditorController::UploadByUrl` |

### Pages, widgets, short links

| Route | Path | Methods | Controller |
|---|---|---|---|
| `shlink_redirect` | `/{slug}` | ANY | `ShortLinkController::Main` |
| `shlink_redirectByLocale` | `/{slug}/{_locale}` | ANY | `ShortLinkController::Main` |
| `widget_page` | `/page/{slug}` | ANY | `Action\Widget\Page` |
| `base_contact` | `/contact` | ANY | `Client\ContactController::Contact` |
| `app_sitemap` | `/sitemap.{extension}` | ANY | `SitemapController::Main` |
| `widget_attachment` | `/attachment/{slug}` | ANY | `WidgetController::Attachment` |
| `app_welcome` | `/` | ANY | `MainController::Index` |

### Language, time zone, signatures, profiler

| Route | Path | Methods | Controller |
|---|---|---|---|
| `_profiler_email` | `/_profiler/email` | ANY | `ProfilerController::Email` |
| `_profiler_email_send` | `/_profiler/email/send` | ANY | `ProfilerController::SendEmail` |
| `_switch_locale` | `/locale/{_locale}` | ANY | `LocalizerController::Locale` |
| `_switch_timezone` | `/timezone/{_timezone}` | ANY | `LocalizerController::Timezone` |
| `base_signature_webhook` | `/signatures/{gateway}/webhook` | POST | `SignatureController::webhook` |

## Voters

The core registers four voters (`config/services/database.php`).

### `AccessVoter`

`Base\Security\Voter\AccessVoter` decides who may browse the site, from
`base.access_restriction` ([Configuration reference](configuration.md#access_restriction)).
`SecuritySubscriber` asks it on each request.

| Attribute | Granted when |
|---|---|
| `ADMIN_ACCESS` | `admin_access` is on, or the user is `ROLE_SUPERADMIN` |
| `USER_ACCESS` | `user_access` is on, or `admin_access` is on and the user is `ROLE_ADMIN` |
| `ANONYMOUS_ACCESS` | `public_access` is on, or (`admin_access` or `user_access` is on) and the user is `ROLE_USER` |
| `MAINTENANCE_ACCESS` | the site is not under maintenance, or `EXCEPTION_ACCESS` |
| `LAUNCH_ACCESS` | the site is launched (`Launcher`), or `EXCEPTION_ACCESS` |
| `EXCEPTION_ACCESS` | the address is under no firewall, or under a firewall not listed in `firewalls`, or one of `exceptions` matches it |

The subject is a `User` (the roles are read on it) or an address (a string, a
`Referrer`; the current address otherwise).

### `AdminVoter`

`BACKEND`, on the back office's and the profiler's routes only: granted to a
signed-in `ROLE_ADMIN`.

### `PermissionVoter`

Any attribute in capitals that does not start with `ROLE_`, `IS_` or `EA_`
(`ARTICLE.PUBLISH`, `COMPLAINT.READ`): granted when the user, or one of its
groups, holds a `Base\Entity\User\Permission` with that tag (compared without
case); a tag ending in `.*` grants every attribute it prefixes (`ARTICLE.*`
grants `ARTICLE.PUBLISH`).

```php
// a controller of the application
$this->denyAccessUnlessGranted('ARTICLE.PUBLISH');
```

### `ComplaintVoter`

`COMPLAINT_READ` and `COMPLAINT_HANDLE`, on a `Base\Entity\User\Complaint` (or
no subject: may this user read complaints at all):

- `ROLE_SUPERADMIN` (the top role) reads and handles them all;
- otherwise the user must reach `ROLE_MODERATOR` or hold the permission tag
  `COMPLAINT.READ` / `COMPLAINT.HANDLE`;
- nobody reads a complaint about themselves;
- a complaint about a user is only for those who outrank them: whose reachable
  roles (the role hierarchy) hold all of theirs and more.
