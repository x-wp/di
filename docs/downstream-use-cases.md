# Downstream use cases: OblakStudio and WooSync

This survey records how plugins in the `oblakstudio` and `woosync` GitHub
organizations use `x-wp/di`, with particular attention to dynamic registration,
deferred loading, and conditions. It is evidence for compatibility and migration
decisions, rather than a proposal to add new APIs.

Research date: **2026-09-28**. Consumer examples refer to the source revisions
linked below. The library comparison uses `beta` at
[`728061c`](https://github.com/x-wp/di/tree/728061cfc2d7651e49fbcabdc24a84a5486c158f).
This was a source inspection; the downstream plugins were not installed or run.
Private/internal repository links require appropriate GitHub access.

## Survey scope and dependency inventory

For OblakStudio, default-branch code search found **14 direct manifest
dependencies**, including two theme packages. The repository-list endpoint
returned 57 accessible repositories but omitted some code-search matches, so
this is an observed inventory, not an exhaustive organization census. For
WooSync, all 11 listed repositories were classified; six plugin/module packages
use the DI API, including packages that rely on a host's runtime dependency.
Selected producer/consumer paths were traced in depth; a manifest-only entry
below does not imply a full runtime audit of that plugin.

The manifest links pin the reviewed revision. Constraints describe source
dependencies, not the version deployed on any particular website.

| OblakStudio repository/package | Kind | Visibility | `require` constraint |
|---|---|---|---|
| [srbtranslatin](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/composer.json) | Plugin | Public | `^1.7` |
| [serbian-addons-for-woocommerce](https://github.com/oblakstudio/serbian-addons-for-woocommerce/blob/27647d95852def8afbf5b1ae99eb4ca48289a499/composer.json) | Plugin | Public | `^1.0` |
| [extremis-core](https://github.com/oblakstudio/extremis-core/blob/0c4315082f432eac191656196585a1916b0c48a4/composer.json) | Theme library | Public | `^1.5.3` |
| [wc-gift-product](https://github.com/oblakstudio/wc-gift-product/blob/a3b01a9760b0d74ef5fd3a2993b1370e6b4cfde1/composer.json) | Plugin | Internal | `^1.0` |
| [triballi-instantly](https://github.com/oblakstudio/triballi-instantly/blob/6c99aecf1c86b1fc1ace3acaf2f705db793a59ea/composer.json) | Plugin | Internal | `^1.0` |
| [wc-attribute-discount](https://github.com/oblakstudio/wc-attribute-discount/blob/c032f10ff3eaa5f52df95feb96f5ec08fd57c7a3/composer.json) | Plugin | Internal | `^1` |
| [tis-b2b-plugin](https://github.com/oblakstudio/tis-b2b-plugin/blob/cd04bca3feab692164c91c60ef6ffa46e81c238b/composer.json) | Plugin | Internal | `^1.4` |
| [wc-unior](https://github.com/oblakstudio/wc-unior/blob/d4b3aefe3cdcf150854d09ce46e76322b9fd1951/composer.json) | Plugin | Internal | `^1.4` |
| [drywall-planner / packages/wp-plugin/gipsaj-planner](https://github.com/oblakstudio/drywall-planner/blob/7300e1c60cd4512055f6fd94c921f25f08b9df46/packages/wp-plugin/gipsaj-planner/composer.json) | Plugin | Internal | `^1.10` |
| [little-my](https://github.com/oblakstudio/little-my/blob/ad262312c3b6b31c4c431db0b6fe41daa154b0f1/composer.json) | Theme | Internal | `^2.0@beta` |
| [forest-customizer](https://github.com/oblakstudio/forest-customizer/blob/147537ce89f97af31d1609b9489ef241b139cd76/composer.json) | Plugin | Internal | `2.0.0-alpha.9` |
| [barel-discount-manager](https://github.com/oblakstudio/barel-discount-manager/blob/2c5b62371d905ee9100f879b7f2808f7692e7650/composer.json) | Plugin | Internal | `v2.0.0-alpha.9` |
| [branblan-product-creator](https://github.com/oblakstudio/branblan-product-creator/blob/b8f35de89ab605060e4e4c128a69c12eed304b56/composer.json) | Plugin | Private | `^1.0` |
| [woosync-unior](https://github.com/oblakstudio/woosync-unior/blob/7cf8c4faad6458e1d2378108787e307bc32c8b10/composer.json) | Plugin | Private | `^1.9` |

| WooSync repository | Visibility | Dependency relationship |
|---|---|---|
| [wp-plugin-main](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/composer.json) | Internal | Production `require: ^1.7`; host application |
| [wp-plugin-wordpress](https://github.com/woosync/wp-plugin-wordpress/blob/07bc538ab6a5d4ec873b9b91437acc63b2a450cc/composer.json) | Private | Production `require: ^1.9`; extends host |
| [wp-plugin-export](https://github.com/woosync/wp-plugin-export/blob/59bbd4c724ac7610b11ed7ba19c5a4a5b3cbd088/composer.json) | Internal | `require-dev: ^1.9`; host supplies runtime |
| [wp-plugin-upload](https://github.com/woosync/wp-plugin-upload/blob/1199af4b55eb5a1d3f3b2da5940fc19583f08e72/composer.json) | Internal | `require-dev: ^1.9`; host supplies runtime |
| [plugin-module-tis](https://github.com/woosync/plugin-module-tis/blob/6a7aed398e2610dfcd38dda12023864ed420bf93/composer.json) | Internal | `require-dev: ^1.8`; library modules expect host |
| [plugin-module-core](https://github.com/woosync/plugin-module-core/blob/0207d25927cebf48ebe9018506aa65e54044e1de/composer.json) | Internal | No direct requirement; shared base uses DI decorators |

WooSync's `wp-plugin-reseller` and `wp-plugin-barel` use the older host module
system; their manifests do not directly require DI. Its `server`,
`logik-middleware`, and `client-php` are outside this WordPress plugin survey.
OblakStudio's `tis-b2b` and `woocommerce-sync-tis` had lockfile or guidance
references without a verified direct manifest requirement; `ttepavac-web`
references the `little-my` theme. These are not counted as additional direct
dependencies. Non-default branches and deployed installations were not audited.

## Read the timing terms separately

The same plugin can use several of these mechanisms together:

| Mechanism | What is delayed or selected | Current `beta` implementation |
|---|---|---|
| Application bootstrap | Container creation and application startup until a WP action | `xwp_load_app()` schedules `xwp_create_app($config)->run()`. Creating an `App` directly does not start it. |
| Module activation | Module initialization and registration of its handlers/imports until its hook and priority | `Invoker::queue_handler()` and `init_module()`. Discovering module definitions is a separate step. |
| Deferred handler (`INIT_AUTO`) | Handler construction, initialization, and callback attachment until its hook | `Invoker::queue_handler()`. |
| Early handler (`INIT_EARLY`) | Callback attachment; initialization occurs at handler registration | `init_eager_handler()` followed by `queue_methods()`. |
| Immediate handler (`INIT_NOW`) | No extra scheduling step after handler registration | Initialize and attach at registration, subject to context and initialization conditions. |
| Lazy handler (`INIT_LAZY`) | Initialization until callback loading requests it | `Filter::load()` requests initialization before attachment; this is not first-invocation loading. |
| Just-in-time handler (`INIT_JIT`) | Initialization until an attached proxy callback is invoked | Attach proxies at the handler hook; `Filter::invoke()` requests initialization. |
| Supplied instance (`INIT_USER`) | Construction belongs to another factory or subsystem | Adopt the instance and attach its callbacks. This is distinct from expanding dynamic hook names. |
| Dynamic hook expansion | Concrete hook names and an extra callback argument come from runtime variables | `Dynamic_Filter::load_hook()` resolves `vars`; map keys substitute into the tag and map values are appended to callback arguments. |

Sources: [bootstrap helpers](../src/Functions/xwp-di-container-fns.php),
[App](../src/App.php), [Invoker](../src/Invoker.php),
[Handler](../src/Decorators/Handler.php), [Filter](../src/Decorators/Filter.php),
[Dynamic_Filter](../src/Decorators/Dynamic_Filter.php), and
[strategy constants and legacy aliases](../src/Interfaces/Can_Handle.php).
The [lifecycle tests](../tests/Integration/Handler_Lifecycle_Test.php) explicitly
distinguish LAZY attachment from JIT invocation, including rejected initialization
and a lazy handler with no callbacks.

Conditions also have separate meanings. A context bitmask selects a request
category; `can_initialize()` gates an initialization attempt; a callback
`conditional` can gate attachment and, for proxied invocation, execution; an
ordinary `if` inside a method gates only that method's work. A service factory
can choose a dependency when resolved without changing the module graph.
An already initialized handler does not keep reevaluating `can_initialize()`.
See [Hook::can_load()](../src/Decorators/Hook.php),
[Handler::load()](../src/Decorators/Handler.php), and
[Filter::load()/invoke()](../src/Decorators/Filter.php).

## Concrete use cases

### Custom readiness events separate application startup from feature startup

SrbTransLatin calls `xwp_load_app()` in its entrypoint. Its root module targets
`plugins_loaded` priority `50`; a root action at priority `100` emits
`srbtranslatin_loaded`. Transliteration and multilingual modules target that
custom event. This is a deliberate readiness boundary with a producer and
consumers, separate from container creation and handler strategies.

Sources: [entrypoint](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/srbtranslatin.php#L29),
[root and event producer](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/src/App.php#L17),
[transliteration module](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/src/Modules/Translit/Translit_Module.php#L23),
and [multilingual module](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/src/Modules/ML/ML_Module.php#L17).

WooSync similarly declares several stages: the host schedules app creation on
`plugins_loaded` at `-3`, its root module targets `woocommerce_loaded`, and a
root callback on `plugins_loaded` emits `woosync_loaded`. The older module helper
attaches singleton creation to `woosync_loaded`; a final root callback sorts
those modules and emits `woosync_modules_loaded`. These are observed declarations,
not a runtime trace proving that every event is reached in every installation.
Sources: [host entrypoint](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/woocommerce-sync-service.php#L36-L59),
[root stages](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/lib/App.php#L130-L168),
and [legacy module helper](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/lib/Utils/woosync-core-utils.php#L23-L38).

### Language state, request context, and callback conditions compose

SrbTransLatin's `Translit_Handler` declares JIT initialization and accepts an
injected `Script_Manager` in `can_initialize()`. The manager permits
transliteration only for Latin script and a supported language. Individual
callbacks add further restrictions: frontend/feed buffering has frontend context;
standard AJAX buffering runs on `admin_init` with an AJAX context and a settings
condition; WooCommerce AJAX buffering runs on `template_redirect` with a
request-state condition. These actions explicitly use proxied invocation.

The compatibility requirement is to retain all three decisions: whether the
handler can initialize, whether a callback belongs to the request context, and
whether that callback's condition permits work. An initialization condition
receiving a service is also a DI call, not necessarily a zero-argument function.

Sources: [handler and callback conditions](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/src/Modules/Translit/Handlers/Translit_Handler.php#L21),
and [language/script decision](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/src/Modules/Translit/Services/Script_Manager.php#L98).

### Optional integrations and engine selection belong to service resolution

SrbTransLatin's multilingual module always declares `stl.language.resolver` as
a factory. Resolving it returns a WPML resolver when `SitePress` exists, or
`null` otherwise. This does not conditionally remove the module or its service
declaration. Its WPML handler separately declares JIT initialization.
Sources: [resolver factory](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/src/Modules/ML/ML_Module.php#L32)
and [WPML handler](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/src/Modules/ML/Handlers/WPML_Handler.php#L16).

Serbian Addons for WooCommerce supplies an `ips.generator` factory that selects
an ImageMagick or GD generator class according to extension availability. Its
proxied `woocommerce_payment_gateways` filter takes the WordPress gateway list
plus an injected gateway instance. The QR handler declares
`wc_payment_gateways_initialized` and JIT, then checks the order/payment context
inside its methods. These illustrate environment-based provider selection,
mixing WP arguments with DI parameters, and per-operation guards.
Sources: [provider and gateway injection](https://github.com/oblakstudio/serbian-addons-for-woocommerce/blob/27647d95852def8afbf5b1ae99eb4ca48289a499/lib/App.php#L48)
and [QR handler](https://github.com/oblakstudio/serbian-addons-for-woocommerce/blob/27647d95852def8afbf5b1ae99eb4ca48289a499/lib/Gateway/Gateway_Payment_Slip_IPS_Handler.php#L20).

### Feature settings gate initialization at different lifecycle points

WooSync's order `Export_Meta` handler declares `init`, priority `99`, and the
legacy `INIT_JUST_IN_TIME` constant. Its `can_initialize()` reads the order
dispatcher's enabled setting. Its checkout action and order-query filter use
proxied invocation. This is a concrete need to attach callbacks while delaying
handler initialization until a relevant operation occurs.

The WordPress integration's `CDN_Image_URL_Handler` instead declares
`INIT_ON_DEMAND`, with an `#[Infuse('cfg.module.wp')]` initialization condition
that requires an enabled setting and a valid URL. Its callbacks rewrite
attachment URLs and image source sets. Under current `beta` semantics,
`INIT_ON_DEMAND` aliases LAZY, while `INIT_JUST_IN_TIME` aliases JIT: the former
initializes during callback attachment, the latter on invocation. They should
not be migrated as one generic "lazy" mode.

The configuration-backed initialization condition is also distinct from the
CDN callback's per-image check. Enabling a feature admits the handler; deciding
whether a particular attachment belongs to that feature remains callback logic.

Sources: [order handler](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/lib/WooCommerce/Order/Export_Meta.php#L18-L67)
and [CDN handler](https://github.com/woosync/wp-plugin-wordpress/blob/07bc538ab6a5d4ec873b9b91437acc63b2a450cc/src/Handlers/CDN_Image_URL_Handler.php#L10-L79).

### Existing WooCommerce objects can acquire attributed callbacks dynamically

WooSync's `Upload_Email_Handler` injects an `Order_Upload_Failed_Email` into a
proxied `woocommerce_email_classes` filter and returns that instance to
WooCommerce. The email class declares `INIT_DYNAMICALY` and calls
`xwp_load_hook_handler($this, 'woosync')` from its constructor, before the parent
email constructor. This is explicit adoption of an existing instance into hook
orchestration, not expansion of dynamic tag names and not a request to construct
a second email object. Current `beta` retains the misspelled legacy constant as
an alias of `INIT_USER`.

The Serbian Addons payment gateway also declares `INIT_DYNAMICALY`. Its
`init_gateway()` rejects disabled/invalid gateways before registering the QR
handler and loading its own instance. Its older calls pass a class string to
`xwp_register_hook_handler()` and omit the application argument from
`xwp_load_hook_handler()`. Current `beta` expects a `Can_Handle` object in the
former and an explicit application string in the latter, so those calls require
migration even though the adoption use case remains relevant.

Sources: [email injection](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Upload/Handlers/Upload_Email_Handler.php#L34-L44),
[email instance adoption](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Upload/Emails/Order_Upload_Failed_Email.php#L25-L82),
[gateway instance adoption](https://github.com/oblakstudio/serbian-addons-for-woocommerce/blob/27647d95852def8afbf5b1ae99eb4ca48289a499/lib/Gateway/Gateway_Payment_Slip.php#L102-L110),
and [current helper signatures](../src/Functions/xwp-di-helper-fns.php).

### Runtime taxonomy maps supply both hook names and callback data

WooSync's main app defines `cfg.mapped.tax` as a DI factory. When resolved, it
enumerates registered taxonomies and keeps the ones supported by its support
manager. Each entry maps a taxonomy name to an array containing the taxonomy and
the selected storage context. The admin handler loads on `init` at priority
`1000`, then uses that token in dynamic column, form, and term-save hooks.

For example, `manage_%s_custom_column` uses the taxonomy key to form the hook
name, accepts three WordPress arguments, and receives the configuration array as
a fourth argument. The same configuration lets the handler choose the relevant
storage path. Other methods skip work for a particular storage context inside
the callback; that is separate from deciding whether the handler may initialize.

This demonstrates **dynamic payloads as well as dynamic names**: values are
structured arrays, not merely strings. Registration must happen after the
taxonomy/support registry is ready, and a cache must preserve the provider token
rather than freezing one request's resolved taxonomy list.

Sources: [configuration producer](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/lib/App.php#L70-L96)
and [dynamic callback consumers](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Core/Handlers/Custom_Term_ID_Admin_Handler.php#L20-L152).

OblakStudio's attribute-discount plugin supplies another map shape: its module
builds `cfg.att.terms` from configured attribute taxonomies, mapping each taxonomy
to a slug. One admin handler on `woocommerce_init` priority `9` expands add/edit
form and created/edited term actions from that token. Sources:
[map producer](https://github.com/oblakstudio/wc-attribute-discount/blob/c032f10ff3eaa5f52df95feb96f5ec08fd57c7a3/src/Modules/Discount/Discount_Module.php#L33)
and [dynamic actions](https://github.com/oblakstudio/wc-attribute-discount/blob/c032f10ff3eaa5f52df95feb96f5ec08fd57c7a3/src/Modules/Discount/Handlers/Discount_Term_Settings_Handler.php#L178).

### Dynamic account endpoints accept callable and container providers

The TIS B2B account module declares endpoint metadata in `cfg.acct.eps`. A helper
combines those endpoint keys with WooCommerce's configured query variables and
returns a URL-slug-to-endpoint-key map. `Dynamic_Action` uses that helper for
`woocommerce_account_%s_endpoint`; the rendering method receives the endpoint
key. A separate `Dynamic_Filter` uses the container metadata map for
`woocommerce_endpoint_%s_title`, whose callback receives endpoint metadata.

The compatibility requirement is to retain callable and container-token
providers, resolve them when their dependencies are ready, and preserve the
distinction between the substituted key and appended value. An endpoint slug
and its logical identifier need not be identical.

Sources: [endpoint definitions](https://github.com/oblakstudio/tis-b2b-plugin/blob/cd04bca3feab692164c91c60ef6ffa46e81c238b/src/Modules/Account/Account_Module.php#L53),
[callable provider](https://github.com/oblakstudio/tis-b2b-plugin/blob/cd04bca3feab692164c91c60ef6ffa46e81c238b/src/Modules/Account/Functions/tis-b2b-account-fns.php#L27),
and [action/filter consumers](https://github.com/oblakstudio/tis-b2b-plugin/blob/cd04bca3feab692164c91c60ef6ffa46e81c238b/src/Modules/Account/Handlers/Endpoint_Handler.php#L187).

### Install/update handlers substitute a plugin slug into a single tag

SrbTransLatin's install module defines `install.slug`; install/update actions
refer to it through `modifiers`. This is a reusable handler with a
container-supplied hook-name fragment, rather than a list of expanded callbacks.
The module is scheduled on `plugins_loaded` at `999` and has an
`is_blog_installed()` initialization condition. Sources:
[module and slug](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/src/Modules/Install/Install_Module.php#L24),
[install action](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/src/Modules/Install/Handlers/Install_Handler.php#L23),
and [update action](https://github.com/oblakstudio/srbtranslatin/blob/d710f8b1d7ffeed14e9c7a10d64829b8d7b880f5/src/Modules/Install/Handlers/Update_Handler.php#L39).

### Screen-dependent handlers need late conditions and derived hook names

WooSync's order-list handler declares admin context and JIT initialization. Its
condition checks the current screen and selects the expected order-list screen
for WooCommerce's HPOS or legacy post storage. The module supplies `screen.order`
through a DI factory; ordinary filters use `modifiers: array('screen.order')`
with `bulk_actions-{%s}` and `handle_bulk_actions-{%s}`.

This is **one derived tag**, unlike the taxonomy example's expansion into many
tags. It also separates resolving the tag's screen identifier from evaluating
whether the current request has reached the relevant screen. Hoisting the latter
check into plugin-file execution would change its timing and available state.

Other cases reinforce the distinction: the status-page handler combines admin
context, the `wc-status` page, and a capability check; the product-list handler
waits for `load-edit.php` and checks both product support and `$typenow`.

Sources: [order-list handler](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Upload/Handlers/Order/Order_List_Page_Handler.php#L24-L49),
[derived bulk-action tags](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Upload/Handlers/Order/Order_List_Page_Handler.php#L112-L133),
[screen ID factory](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Upload/Upload_Module.php#L34-L42),
[status condition](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Core/Handlers/Status_Page_Handler.php#L19-L31),
and [product-list condition](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Core/Handlers/Product_Sync_Data_Handler.php#L19-L27).

### Scheduled jobs combine DI timing with a separate work scheduler

WooSync's sync module defines a recurring cleanup job in `sync.jobs`. Its job
scheduler handler initializes on `init` at priority `9`; a callback at priority
`10` schedules jobs outside frontend context. A settings-save action reschedules
them. When scheduling would happen while the same job hook is executing, the
handler defers the scheduling call to `shutdown`.

The cleanup handler separately uses `INIT_JUST_IN_TIME` and a proxied callback
on `woosync_sync_log_cleanup`. There are three distinct delays here: WordPress
startup ordering, Action Scheduler's future job execution, and DI construction
when the job callback is first needed. The `shutdown` guard is ordinary callback
logic, not another DI initialization strategy.

Sources: [job definitions](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Sync/Sync_Module.php#L23-L47),
[scheduler](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Sync/Handlers/Sync_Job_Scheduler.php#L10-L66),
and [cleanup handler](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Sync/Jobs/Sync_Log_Cleaner.php#L14-L63).

### Satellite plugins contribute imports before the host container is built

WooSync Export's bootstrap waits until `plugins_loaded`, checks for the host app
class, and installs `xwp_extend_import_woosync` before the host's scheduled
container creation at priority `-3`. Its import callback checks the target module,
attempts compatibility alias registration, and appends its module only if the
normalized class name is absent. A missing host or alias ownership conflict
results in an admin notice and no new import.

The useful contract is **ordered, conditional, idempotent composition across
plugins**. Deferring this bootstrap also lets the shared autoloader settle which
package version supplies each class before compatibility decisions are made.

This is a version-specific consumer contract. The inspected Export callback
requires two filter arguments and returns a list of class names. Current `beta`
calls that filter with one argument and expects extension descriptors containing
`id`, `module`, `file`, and `version`. Copying this satellite callback into `beta`
unchanged would therefore be incompatible. The example motivates preserving or
explicitly migrating the extension boundary, not assuming the two APIs match.

Sources: [Export bootstrap](https://github.com/woosync/wp-plugin-export/blob/59bbd4c724ac7610b11ed7ba19c5a4a5b3cbd088/src/Bootstrap/Bootstrap_Module.php#L15-L77),
[host startup](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/woocommerce-sync-service.php#L36-L59),
and [current beta extension parser](../src/Hook/Parser.php).

Upload follows a similar satellite bootstrap but also declines to import when
an embedded Upload module already owns the feature, and checks host/WooCommerce
compatibility. The inspected main plugin still contains embedded modules; the
presence of satellite repositories does not prove extraction is complete in
every deployment. See [Upload's bootstrap](https://github.com/woosync/wp-plugin-upload/blob/1199af4b55eb5a1d3f3b2da5940fc19583f08e72/src/Bootstrap/Bootstrap_Module.php#L11-L50).

The older WordPress add-on uses a different released API:
`xwp_extend_app(container: 'woosync', module: WP_Module::class)`. It contributes
config and service definitions and declares its module on `woosync_loaded`.
Current `beta` instead declares `xwp_extend_app(array $extension, string
$application)`, so the named-argument call also needs migration. Sources:
[add-on entrypoint](https://github.com/woosync/wp-plugin-wordpress/blob/07bc538ab6a5d4ec873b9b91437acc63b2a450cc/woocommerce-sync-wp.php#L28-L30),
[module definitions](https://github.com/woosync/wp-plugin-wordpress/blob/07bc538ab6a5d4ec873b9b91437acc63b2a450cc/src/WP_Module.php#L12-L75),
and [beta helper signature](../src/Functions/xwp-di-container-fns.php).

### REST, AJAX, and CLI expose different portions of the same application

WooSync Export contributes a REST controller on `rest_api_init` in REST context
and a CLI handler on `cli_init` in CLI context. Main's sync AJAX handler declares
request methods, capabilities, nonce handling, and request variables through
`Ajax_Handler`/`Ajax_Action`. These are specialized callback contracts in addition
to request selection; they cannot be reduced to ordinary actions without
preserving their argument and authorization behavior.

Sources: [REST controller](https://github.com/woosync/wp-plugin-export/blob/59bbd4c724ac7610b11ed7ba19c5a4a5b3cbd088/src/Controllers/Export_Controller.php#L24-L28),
[CLI handler](https://github.com/woosync/wp-plugin-export/blob/59bbd4c724ac7610b11ed7ba19c5a4a5b3cbd088/src/Handlers/Export_CLI_Handler.php#L19-L37),
and [AJAX handler](https://github.com/woosync/wp-plugin-main/blob/414f2d25cd5e1cf0b43da18ef49b01c489946a9d/src/Modules/Core/Handlers/Sync_Ajax_Handler.php#L10-L69).

### Theme startup and activation show the limits of a single bootstrap model

Two theme consumers are useful comparison cases even though the survey focuses
on plugins. Extremis Core registers composition filters and schedules its app
on `after_setup_theme` at `-1001`; its custom `Module` subclass targets the same
action at `-1000` and merges imported handlers/configuration. Little My,
which requests `^2.0@beta`, explicitly calls `xwp_create_app(...)->run()` and
disables app/hook caching in the inspected bootstrap. Its module is scheduled on
`after_setup_theme`. These demonstrate both extension through a custom decorator
and explicit creation/startup in a beta consumer, without proving compatibility
with every intermediate beta revision.

Sources: [Extremis builder](https://github.com/oblakstudio/extremis-core/blob/0c4315082f432eac191656196585a1916b0c48a4/src/Builder.php#L63),
[custom theme decorator](https://github.com/oblakstudio/extremis-core/blob/0c4315082f432eac191656196585a1916b0c48a4/src/Decorators/Theme_Module.php#L22),
[Little My bootstrap](https://github.com/oblakstudio/little-my/blob/ad262312c3b6b31c4c431db0b6fe41daa154b0f1/functions.php#L24),
and [theme module](https://github.com/oblakstudio/little-my/blob/ad262312c3b6b31c4c431db0b6fe41daa154b0f1/src/Theme.php#L38).

The planner plugin schedules its app on `woocommerce_loaded` at `-2`, its root
module at `20`, and its readiness notification at `30`. Within initialization,
it delays textdomain loading until `init`. Its activation callback deliberately
uses a static rewrite-rule helper because DI has not booted yet. This is a
concrete case where activation-time work must remain usable outside the normal
application lifecycle. Sources:
[planner bootstrap and activation](https://github.com/oblakstudio/drywall-planner/blob/7300e1c60cd4512055f6fd94c921f25f08b9df46/packages/wp-plugin/gipsaj-planner/gipsaj-planner.php#L33),
and [module timing](https://github.com/oblakstudio/drywall-planner/blob/7300e1c60cd4512055f6fd94c921f25f08b9df46/packages/wp-plugin/gipsaj-planner/src/App.php#L30).

## Implications for the definition/lifecycle migration

The following are requirements inferred from the inspected consumers. They are
not claims that every consumer has been ported or that these scenarios all have
integration coverage in this checkout.

| Behavior to preserve or explicitly migrate | Evidence and useful verification scenario |
|---|---|
| App creation, module readiness, and handler timing remain distinct | SrbTransLatin readiness event; WooSync stages; Little My `create()->run()`. Observe event ordering and creation counts independently. |
| Conditions execute when the required state is available | Language-manager and screen predicates. A JIT rejection must not require handler construction; an eligible later invocation can retry. |
| Callback conditions remain separate from initialization conditions | SrbTransLatin frontend and AJAX callbacks. Admitting the handler does not admit every callback. |
| Dynamic providers preserve keys, values, and extra-argument position | Taxonomy configuration arrays and account endpoint maps. Verify callable providers, DI tokens, empty maps, and non-string payloads. |
| Single-tag modifiers remain distinct from dynamic expansion | Install slug and HPOS screen token. Verify the resolved tag and original WP argument order. |
| Supplied instances keep their identity and construction lifecycle | WooCommerce email/gateway adoption. Attach callbacks to the supplied object; migrate older helper signatures explicitly. |
| Definitions do not become a cached snapshot of request eligibility | Optional WPML resolver, settings predicates, taxonomy providers, and request contexts. Compare cold/warm caches across relevant request types. |
| Cross-plugin composition preserves registration order and ownership checks | Export/Upload satellites. Exercise missing host, duplicate import, and conflicting ownership; migrate the verified extension signature/data-shape differences. |
| Specialized callbacks retain their contracts | AJAX request/nonce/capability metadata, REST routes, CLI commands, and proxied DI arguments. |
| Work scheduled outside DI keeps its own lifecycle | Action Scheduler jobs, `shutdown` deferral, activation helpers, and textdomain loading. |

Current `beta` still uses runtime decorators, `Hook\Parser`, `Hook\Factory`, and
`Invoker`. Its `CallbackDefinition::from_data()` accepts only plain `Filter` and
`Action` metadata; it is not yet a general replacement for dynamic, AJAX, REST,
or CLI decorators. `ModuleDefinition` carries composition metadata, and
`HandlerDefinition` is not a complete replacement for every runtime attribute
field. The consumer inventory therefore does not justify treating the definition
migration as complete. Sources: [callback definition](../src/Definition/CallbackDefinition.php),
[module definition](../src/Definition/ModuleDefinition.php),
[handler definition](../src/Definition/HandlerDefinition.php), and
[parser](../src/Hook/Parser.php).

Existing tests provide a starting point for app startup, contexts, strategies,
and retries: [app bootstrap](../tests/Integration/App_Bootstrap_Test.php),
[handler lifecycle](../tests/Integration/Handler_Lifecycle_Test.php),
[handler contexts](../tests/Integration/Handler_Context_Test.php),
[retry behavior](../tests/Integration/Handler_Retry_Test.php), and
[default scheduling](../tests/Integration/Default_Schedule_Test.php).
Their presence is not proof that the downstream examples above pass unchanged.
Use this survey alongside the [definition split plan](definition-split-plan.md),
which distinguishes agreed design from implemented behavior.
