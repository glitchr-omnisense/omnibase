<?php

// What tests/Console/tasks-without-framework.php returns: omnibase's console tasks run without a framework (plan "omnibase agnostic", step F5e, slice 3).

return [
  'report' => [
    'text' => 'one line
no end, then the end
[WARNING] a task warned
[NOTICE] a note
[SUCCESS] done
a | b
✓ | x
',
    'verbose' => 'one line
no end, then the end
verbose
[WARNING] a task warned
[NOTICE] a note
[SUCCESS] done
a | b
✓ | x
',
    'kinds' => [
      0 => 'text',
      1 => 'text',
      2 => 'text',
      3 => 'text',
      4 => 'warning',
      5 => 'notice',
      6 => 'success',
      7 => 'table',
    ],
    'status' => 2,
  ],
  'crud' => 'CRUD controller list: 
 * App\\Controller\\Admin\\Crud\\Blog\\PostCrudController : 
	 - @admin[fr-FR].crud.admin.crud.blog.post.index.title = "Billets"
	 - @admin[en-GB].crud.admin.crud.blog.post.index.title = "no translation found" (possible entity fallback)
',
  'crud, one language' => 'CRUD controller list: 
 * App\\Controller\\Admin\\Crud\\Blog\\PostCrudController : @admin[fr-FR].crud.admin.crud.blog.post.index.title = "Billets"
',
  'crud, a language the site has not got' => 'Locale not found in the list of available locale: [fr-FR,en-GB]',
  'entities' => [
    'application' => [
      0 => 'Entity list: ',
      1 => ' * App\\Entity\\Blog (namespace) : ',
      2 => '	 - @entities[fr-FR].blog._singular = "no translation found"',
      3 => '	 - @entities[en-GB].blog._singular = "no translation found"',
      4 => ' * App\\Entity\\Blog\\Post : ',
      5 => '	 - @entities[fr-FR].blog.post._singular = "billet"',
      6 => '	 - @entities[en-GB].blog.post._singular = "post"',
    ],
    'core' => true,
  ],
  'entities, restricted' => 'Entity list: App\\Entity\\Blog\\Post
 * App\\Entity\\Blog\\Post : 
	 - @entities[fr-FR].blog.post._singular = "billet"
	 - @entities[en-GB].blog.post._singular = "post"
',
  'enums' => [
    'application' => [
      0 => 'Enum list: ',
      1 => '',
      2 => ' * App\\Enum\\Mood : ',
      3 => '	 - @enums[fr-FR].enum.mood._singular = "no translation found"',
      4 => '	 - @enums[en-GB].enum.mood._singular = "no translation found"',
      5 => '	 * App\\Enum\\Mood::happy : ',
      6 => '		 - @enums[fr-FR].enum.mood.happy._singular = "no translation found"',
      7 => '		 - @enums[en-GB].enum.mood.happy._singular = "no translation found"',
      8 => '	 * App\\Enum\\Mood::sad : ',
      9 => '		 - @enums[fr-FR].enum.mood.sad._singular = "no translation found"',
      10 => '		 - @enums[en-GB].enum.mood.sad._singular = "no translation found"',
      11 => '',
    ],
    'core' => true,
  ],
  'controllers' => 'Controller list:
 * App\\Controller\\BlogController::post : 
	 - @controllers[fr-FR].blog_post.title = "Le billet"
	 - @controllers[en-GB].blog_post.title = "The post"
 * Base\\Controller\\UserController::profile : 
	 - @controllers[fr-FR].user_profile.title = "no translation found"
	 - @controllers[en-GB].user_profile.title = "no translation found"
',
  'settings' => 'Setting list: site.menu
 * site.title         [fr-FR] : Les Ringos
 * site.title         [en-GB] : The Ringos
 * site.menu [single,fr-FR] : 
   {
	["home","/"],
	["blog","/blog"]
   }
',
  'settings, raw' => 'Setting list: site.menu
 * site.title.label [fr-FR] : Titre
 * site.title.label [en-GB] : * no entry found *
 * site.title.help  [fr-FR] : * no entry found *
 * site.title.help  [en-GB] : * no entry found *

 * site.menu.label  [fr-FR] : * no entry found *
 * site.menu.label  [en-GB] : * no entry found *
 * site.menu.help   [fr-FR] : * no entry found *
 * site.menu.help   [en-GB] : * no entry found *

',
  'sessions' => [
    'text' => ' [INFO] Session files cleared from <root>/var/sessions.

',
    'left' => [
      0 => 'keep',
    ],
  ],
  'cache:clear' => [
    'first' => '
 // Optional cache warmers disabled.
',
    'second, the lock held' => 'Another cache:clear is already running; skip.
Cache clear aborted: another process is already clearing the cache.
',
    'second status' => 0,
    'host steps' => [
      0 => 'clear',
      1 => 'sessions',
    ],
    'clears' => 2,
    'first clear' => false,
    'failing host status' => 3,
    'lock left' => false,
  ],
  'demo:reset' => [
    'outside demo' => [
      0 => 'demo:reset runs in the "demo" environment only: it empties the database it is given.
',
      1 => 1,
    ],
    'in debug' => 'The demo environment does not start in debug: set APP_DEBUG=0 (bin/console --env=demo --no-debug).
',
    'without a lock' => 'demo:reset needs symfony/lock (composer require symfony/lock): two resets must never run at once.
',
    'commands run' => [
    ],
  ],
  'uploader:entities' => '
 Looking for "Base\\Database\\Attribute\\Uploader" attributes..
	Uploader attribute not found for "App\\Entity\\Nowhere"
	Uploader attribute not found for "Base\\Entity\\Nowhere"

                                               
 [OK] 0 fields found: 0 file(s); 0 orphan(s) ! 
                                               

',
  'uploader:images' => '
 Looking for "Base\\Database\\Attribute\\Uploader" attributes..
	Uploader attribute not found for "App\\Entity\\Nowhere"
	Uploader attribute not found for "Base\\Entity\\Nowhere"

                                  
 [OK] 0 fields found: 0 file(s) ! 
                                  

',
  'uploader:images:crop, not an image' => [
    0 => '
                                                           
 [ERR] Entity must inherit from "Base\\Entity\\Layout\\Image" 
                                                           

',
    1 => 1,
  ],
  'base:mapping' => [
    'first' => 'Complete mapping applied:',
    'user' => [
      0 => ' * Application file found: App\\Entity\\User (no alias)',
      1 => '  * Application file found: App\\Entity\\User (no alias)',
    ],
    'summary' => [
      0 => '- N alias(es) from \\Base to \\App applied.',
      1 => '- There is/are N overriding exception(s). Exception list below:',
    ],
  ],
  'icon:enums' => 'Enum list: Base\\Enum\\UserRole
 * Base\\Enum\\UserRole
	ROLE_ADMIN      : [fa-solid fa-crown,fa-solid fa-star]
	ROLE_EDITOR     : [fa-solid fa-hard-hat,fa-solid fa-hard-hat]
	ROLE_SUPERADMIN : [fa-solid fa-cog,fa-solid fa-user-cog]
	ROLE_USER       : [fa-solid fa-user,fa-solid fa-user-tag]
',
  'entity:discriminator' => [
    'an entity' => '',
    'not one' => 'Class "App\\Nowhere" is not an entity.',
  ],
  'icon:entities' => [
    'not an entity' => 'Entity "App\\Nowhere" doesn\'t exists',
    'no icons' => 'Entity "App\\Entity\\Blog\\Post" doesn\'t implement Base\\Service\\Model\\IconizeInterface',
  ],
  'autocomplete:entities, no picker' => 'Entity "App\\Entity\\Blog\\Post" doesn\'t implement Base\\Service\\Model\\AutocompleteInterface',
  'app:notifier' => 'Available templated notifications:
 * welcome
 * goodbye
',
  'thread:publishable' => '
                                                            
 [OK] 0 scheduled thread(s) found: 0 thread(s) publishable. 
                                                            

',
  'trash:purge' => [
    'empty' => 'Nothing to purge. The trash holds 0 entries, none past its grace period.
',
    'dry run' => '  would destroy App\\Entity\\Blog\\Post #7 "Hello" (expired 2026-01-02 03:04)

Dry run. 1 entry would be destroyed. Pass --force to do it.
',
    'forced' => '  destroying App\\Entity\\Blog\\Post #7 "Hello" (expired 2026-01-02 03:04)

Purged. 1 entity destroyed.
',
  ],
  'user:create' => [
    'taken' => [
      0 => '[ERROR] An account already has the username "someone" or the address taken@example.org.
',
      1 => 1,
    ],
    'unknown role' => [
      0 => '[ERROR] Unknown role "NOBODY".
',
      1 => 1,
    ],
    'no notifier' => 'user:create e-mails the account through omnibase\'s Notifier, which this host has not: pass --no-email.',
    'password change token' => 'change-password',
  ],
  'base:mailer:trap' => [
    'status' => '[WARNING] Trap ARMED since <time> (bare) - no mail is leaving this application.
 Held messages: 0
 Directory:     <root>/var/mail-trap
',
    'list' => '[SUCCESS] Nothing held.
',
    'show' => [
      0 => '[ERROR] Which one? Pass an id from "base:mailer:trap list".
',
      1 => 1,
    ],
    'unknown' => [
      0 => '[ERROR] Unknown action "bogus". Try: status, on, off, list, show, purge, release.
',
      1 => 1,
    ],
    'armed' => [
      0 => '[WARNING] Trap ARMED. Nothing will be delivered until "base:mailer:trap off".
 Already-queued mail is caught too: the trap sits at send time, not at queue time.
',
      1 => true,
    ],
    'disarmed' => [
      0 => '[SUCCESS] Trap off - mail is being delivered again.
',
      1 => false,
    ],
    'release, nothing named' => [
      0 => '[ERROR] Pass an id from "base:mailer:trap list", or --all.
',
      1 => 1,
    ],
  ],
  'timemachine' => [
    'storage' => 'Available storage place(s):
 * local (stdClass)
',
    'snapshot' => 'Available database connection(s):
* default (stdClass) <-- selected 

Storage filesystem: 
* [local] /backups/ <-- selected 

Available snapshot(s) prefixed by "prod" in local
[Fri, Jan 2, 2026]
* [0] /backups/site-20260102.tar.gz

',
    'backup' => [
      0 => 'Available database connection(s):
* default (stdClass) <-- selected 

Storage filesystem: 
* [local] /backups/ <-- selected 

User configuration will be included in the backup

Backup completed successfully.

',
      1 => 0,
    ],
    'restore' => [
      0 => 0,
      1 => [
        0 => 'You are about to backup this application and its database. Do you wish to continue [Y/n] ? ',
        1 => 'You are about to restore the latest version. Do you wish to continue ? [y/N] ',
        2 => 'Do you wish to restore application from tarball ? [y/N] ',
        3 => 'Do you wish to restore database from tarball ? [y/N] ',
      ],
    ],
    'backup, no storage' => [
      0 => 1,
      1 => true,
    ],
  ],
];
