# سند فنی و دامنهٔ توانمندی‌های Nakhostin Performance Engine

این سند برای مدیر فنی، توسعه‌دهنده، مسئول زیرساخت و ارزیاب امنیت نوشته شده است تا بدون نیاز به خواندن تمام کدها، معماری، جریان داده، قابلیت‌های عملیاتی و مرزهای نسخهٔ فعلی **Nakhostin Performance Engine (NPE)** را بشناسد. منظور از «هوشمندی» در NPE، جمع‌آوری شواهد ساختاری و ساخت یک برنامهٔ محافظه‌کارانه است؛ هر قابلیتی که صرفاً تحلیلی است در این سند صریحاً از بهینه‌سازی فعال جدا شده است.

## ۱. نمای کلی محصول

NPE یک افزونهٔ ماژولار برای WordPress و WooCommerce است که عملیات زیر را در یک لایهٔ مستقل گرد هم می‌آورد:

- شناخت ساختار DOM و الگوهای تکرارشوندهٔ صفحات؛
- تحلیل محافظه‌کارانهٔ CSS، فونت‌ها و JavaScript؛
- مدل‌سازی اجزای صفحه و وابستگی منابع؛
- کش صفحه و fragment در سطح برنامه؛
- پاک‌سازی هدفمند، پیش‌گرم‌سازی و پردازش صف‌های پس‌زمینه؛
- همکاری اختیاری با LiteSpeed Cache از طریق hookهای عمومی؛
- نمونه‌برداری کم‌هزینه از زمان تولید پاسخ در backend؛
- رابط مدیریت فارسی‌محور، RTL و مبتنی بر اجزای بومی WordPress.

اصل طراحی افزونه **Fail Open** است: اگر تحلیل، ذخیره‌سازی، ساخت فایل یا کش با خطا روبه‌رو شود، صفحه و منابع اصلی WordPress باید بدون بهینه‌سازی NPE همچنان قابل استفاده باشند.

## ۲. پشتهٔ فنی و الزامات اجرا

| لایه | فناوری یا API | کاربرد در NPE |
| --- | --- | --- |
| زبان سرور | PHP 7.4+، namespace و type declaration | منطق ماژول‌ها، value objectها و adapterها |
| بستر | WordPress 6.4+ | Hook API، Settings API، Cron API، HTTP API، Object Cache API و Filesystem boundary |
| بارگذاری کلاس | Composer PSR-4 با autoloader محدود جایگزین | نگاشت `Nakhostin\PerformanceEngine\` به `src/` بدون وابستگی runtime اجباری به Composer |
| تحلیل سند | PHP DOM extension | تجزیهٔ HTML محدودشده و تولید مانیفست ساختاری |
| ذخیره‌سازی سبک | WordPress Options و Transients | تنظیمات، مانیفست‌های محدود، صف‌ها، lockها و وضعیت عملیات |
| کش سریع | WordPress Object Cache | backend اختیاری برای fragmentها در صورت وجود object cache پایدار |
| کش فایل | فایل‌های اتمیک و content-hash | ذخیرهٔ page/fragment cache و artifactهای تولیدی در مسیر ثابت متعلق به NPE |
| کار پس‌زمینه | WP-Cron | تحلیل دسته‌ای، purge و warmup با batch محدود و deduplication |
| رابط مدیریت | WordPress Admin CSS، Dashicons و JavaScript بدون framework | UI واکنش‌گرا، دسترس‌پذیر، RTL/LTR و بارگذاری‌شده فقط در صفحات NPE |
| بومی‌سازی | WordPress i18n، PO/POT و PHP translation catalog | فارسی `fa_IR` و انگلیسی پیش‌فرض با text domain برابر `nakhostin-performance-engine` |
| کیفیت | PHPUnit، PHPCS/WPCS و micro-benchmark | آزمون واحد/یکپارچه، سازگاری کدنویسی و سنجش سربار مسیرهای مهم |

افزونه در production هیچ package اجباری Composer ندارد. Redis، Memcached، WooCommerce، Elementor، WoodMart و LiteSpeed Cache همگی اختیاری‌اند و نبودن آن‌ها نباید باعث خطای fatal شود.

## ۳. معماری ماژولار

ساختار کد بر اساس مسئولیت تفکیک شده است:

- **Core**: bootstrap، lifecycle، migration، feature flag و composition root؛
- **Contracts**: interfaceهای باریک بین ماژول‌ها؛
- **Infrastructure**: تنظیمات، diagnostics و logger؛
- **DOM**: تحلیل ساختاری، امضا، ذخیره‌سازی، صف و یادگیری خودکار؛
- **CSS**: تحلیل selector، کشف stylesheet و هوشمندی فونت؛
- **JavaScript**: کشف asset، dependency graph، safety policy، planner و bundle writer؛
- **Components**: تعریف component، registry، signature و bundle index؛
- **Cache**: policy، key، store، fragment، dependency graph، purge و warmup؛
- **Performance**: نمونه، ذخیره‌سازی و تجمیع اندازه‌گیری backend؛
- **Integrations**: adapterهای جدا برای LiteSpeed، WooCommerce، Elementor و WoodMart؛
- **Admin**: صفحه‌های مدیریت، عملیات capability/nonce-protected و گزارش‌های انسانی.

`Core\Plugin` تنها composition root است و concrete serviceها را به یکدیگر متصل می‌کند. منطق هر قابلیت در ماژول خودش باقی می‌ماند و integrationهای اختیاری به هسته نشت نمی‌کنند. Service registry سبک است و از reflection یا dependency injection خودکار استفاده نمی‌کند.

## ۴. چرخهٔ راه‌اندازی و تنظیمات

فایل اصلی افزونه ثابت‌های نسخه، مسیر و URL را تعریف می‌کند، autoloader را فعال می‌کند و activation/deactivation hookها را ثبت می‌کند. فعال‌سازی، migrationهای ترتیبی و idempotent را اجرا می‌کند. غیرفعال‌سازی فقط scheduleهای متعلق به NPE را پاک می‌کند و حذف داده‌ها صرفاً هنگام uninstall و پس از انتخاب صریح مدیر انجام می‌شود.

`Infrastructure\Settings` مرز واحد تنظیمات است:

1. مقدارهای پیش‌فرض کامل را فراهم می‌کند؛
2. کلید ناشناخته را حذف می‌کند؛
3. boolean و مقدارهای محدود را normalize می‌کند؛
4. ذخیره‌سازی را به Settings API و nonce استاندارد WordPress می‌سپارد؛
5. feature flag را تنها در صورتی مؤثر می‌کند که قابلیت در کد نیز «موجود» اعلام شده باشد.

این کنترل دو مرحله‌ای مانع آن می‌شود که با دست‌کاری مستقیم option، ماژول ناقص فعال شود. قابلیت‌های runtime پرریسک به‌صورت پیش‌فرض خاموش‌اند.

## ۵. DOM Intelligence و تحلیل خودکار

### جریان تحلیل

1. یک URL عمومی، هم‌مبدأ، بدون query حساس و واجد شرایط مشاهده می‌شود.
2. URL پس از normalize شدن در صف محدود و deduplicated قرار می‌گیرد.
3. WP-Cron تعداد کمی job را در هر batch اجرا می‌کند تا درخواست بازدیدکننده سنگین نشود.
4. پاسخ با timeout و سقف اندازه دریافت می‌شود؛ HTML کامل تنها در حافظه باقی می‌ماند.
5. `DOMAnalyzer` داده‌های ناپایدار و شخصی را کنار می‌گذارد و یک `DOMManifest` فشرده می‌سازد.
6. componentها، stateهای ساختاری، stylesheetها و scriptهای مشاهده‌شده برای مصرف تحلیل‌گرهای بعدی ثبت می‌شوند.

درخواست‌های مدیریت، login، cart، checkout، account، preview، REST/AJAX، کاربران واردشده و درخواست‌های session-bearing وارد مسیر یادگیری عمومی نمی‌شوند. صف دارای lock، retry محدود، اندازهٔ محدود، progress، شمارندهٔ صفحات تحلیل‌شده و تخمین زمان پایان است.

پس از پاک‌سازی کامل کش NPE یا دریافت signal عمومی purge از LiteSpeed، URLهای شناخته‌شده با کنترل نرخ دوباره در صف قرار می‌گیرند. هدف این رفتار، بازسازی تدریجی دانش صفحه بدون crawler تهاجمی است.

### خروجی

مانیفست DOM شامل شواهد ساختاری و signature پایدار است، نه متن صفحه، مقدار فرم، query string یا HTML کامل. این خروجی مبنای تشخیص template/component و برنامه‌ریزی منابع است و به‌تنهایی markup سایت را تغییر نمی‌دهد.

## ۶. CSS و مدیریت فونت

### تحلیل CSS

`StylesheetSourceCollector` فقط stylesheetهای محلی و قابل اعتماد را جمع‌آوری می‌کند. `CSSAnalyzer` selectorهای قابل تحلیل را با مانیفست DOM مقایسه و آن‌ها را در گروه‌های استفاده‌شده، استفاده‌نشده یا نامطمئن طبقه‌بندی می‌کند. selectorهای dynamic، pseudo-stateها و ساختارهای مبهم محافظه‌کارانه نگه داشته می‌شوند.

در نسخهٔ فعلی حذف عمومی CSS **تشخیصی و review-only** است؛ NPE صرفاً به دلیل مشاهده‌نشدن یک selector در یک snapshot، آن را خودکار از سایت حذف نمی‌کند.

### هوشمندی فونت

`FontAnalyzer` قواعد `@font-face` را بررسی می‌کند و family، weight، style، format و URL منبع را به شکل normalizeشده در `FontManifest` ثبت می‌کند. `FontStorage` مشاهدات صفحات مختلف را به یک نقشهٔ محدود site-wide تبدیل می‌کند.

`FontLoadingOptimizer` تنها برای stylesheetهای font-only که تحلیل و تأیید شده‌اند، تصمیم صفحه‌محور می‌گیرد. قواعد ایمنی آن عبارت‌اند از:

- stylesheet ترکیبی یا ناشناخته بدون تغییر باقی می‌ماند؛
- صفحهٔ تحلیل‌نشده و کاربر واردشده از optimization عبور می‌کند؛
- font face فقط وقتی انتخاب می‌شود که شواهد همان صفحه استفاده از family/weight/style را نشان دهد؛
- در نبود اطمینان، stylesheet اصلی حفظ می‌شود؛
- URL فونت از ورودی آزاد کاربر ساخته نمی‌شود.

این طراحی از بارگیری همهٔ وزن‌های یک خانواده در همهٔ صفحات جلوگیری می‌کند، اما fallback ایمن را بر کاهش تهاجمی request مقدم می‌داند.

## ۷. JavaScript Intelligence

JavaScript pipeline از metadata ثبت‌شده در WordPress و scriptهای مشاهده‌شده استفاده می‌کند:

1. `ScriptDiscovery` handle، source، dependency، footer/header placement و metadata را کشف می‌کند؛
2. `DependencyGraph` ترتیب معتبر را محاسبه و cycle را گزارش می‌کند؛
3. `ScriptSafetyPolicy` منابع external، critical، inline-configured، ناشناخته یا مبهم را از تغییر تهاجمی خارج می‌کند؛
4. `JavaScriptPlanner` برنامه‌های core، component و page را می‌سازد؛
5. bundle writer فقط artifact کلاسیک و content-hashed را در پوشهٔ قابل اعتماد تولید می‌کند.

ساخت bundle به معنی جایگزینی خودکار scriptهای frontend نیست. موتور runtime نسخهٔ ۲ فقط تصمیم‌های ذخیره‌شده و تازهٔ defer و unload را با کنترل مستقل اجرا می‌کند؛ جایگزینی عمومی bundle همچنان عمداً انجام نمی‌شود. scriptهای ردشده یا نامطمئن با handle اصلی باقی می‌مانند و در شکست build نیز asset اصلی WordPress استفاده می‌شود.

## ۸. Component Intelligence

Component Registry شواهد DOM را به واحدهای عملکردی قابل استفاده مجدد تبدیل می‌کند. هر component می‌تواند شناسه، detector، وابستگی asset و signature پایدار داشته باشد. Adapterهای WooCommerce، Elementor و WoodMart در صورت وجود محصول مربوطه، componentهای شناخته‌شده را به registry معرفی می‌کنند.

`BundleIndex` رابطهٔ component، صفحه و برنامهٔ asset را نگه می‌دارد تا تغییر یک component فقط برنامه‌های مرتبط را invalid کند. این لایه، metadata و dependency را مدیریت می‌کند و مالک تغییر DOM یا اجرای کد third-party نیست.

## ۹. معماری کش

### کش کامل صفحه

جریان page cache عبارت است از:

`CacheRequestFactory → QueryPolicy/CachePolicy → CacheKeyGenerator → PageCache → PageCacheStoreInterface`

- request factory ورودی HTTP را به مدل محدود داخلی تبدیل می‌کند؛
- policy فقط پاسخ عمومی و قابل اشتراک را می‌پذیرد؛
- key generator ابعاد allowlisted مانند site/locale/path را normalize و hash می‌کند؛
- filesystem store با temporary file و atomic rename می‌نویسد؛
- payload دارای version، content hash و guard ابتدایی `<?php exit; ?>` است.

GET/HEAD عمومی می‌تواند cache شود؛ request احراز هویت‌شده، cookie شخصی، cart/checkout/account، authorization، query حساس، پاسخ `Set-Cookie`، `private/no-store`، nonce/password markup و admin toolbar bypass می‌شود. بنابراین محتوای یک کاربر نباید از طریق کش عمومی به کاربر دیگر منتقل شود.

### Fragment و Object Cache

fragmentها public یا private scope دارند. scope خصوصی پیش از ذخیره SHA-256 می‌شود. factory در صورت تأیید object cache پایدار از API عمومی WordPress استفاده می‌کند و در غیر این صورت به backend فایل اتمیک بازمی‌گردد. NPE هرگز برای invalidation محدود، کل object cache وردپرس را flush نمی‌کند.

### Smart Purge و Warmup

dependency graph رابطهٔ content، taxonomy، component، asset، page و fragment را ثبت می‌کند. هنگام تغییر، `SmartPurgeManager` گره‌های وابسته را تا حد محدود resolve می‌کند و jobهای deduplicated می‌سازد. full purge فقط برای رویداد صریح یا سراسری است.

Warmup در صف مستقل، با URL هم‌مبدأ، batch کوچک، timeout و retry محدود اجرا می‌شود. purge و warmup در response کاربر عملیات سنگین انجام نمی‌دهند.

## ۱۰. یکپارچه‌سازی‌ها

### LiteSpeed Cache

NPE فقط از action/filterهای عمومی LiteSpeed استفاده می‌کند و سه سیاست دارد:

- **Compatible**: مالکیت page cache عمومی را به LiteSpeed می‌سپارد و از کار تکراری جلوگیری می‌کند؛
- **Cooperative**: TTL، vary، tag و purge محدود را از طریق hookهای عمومی هماهنگ می‌کند؛
- **Independent**: هیچ hook مربوط به LiteSpeed منتشر نمی‌کند.

NPE فایل یا تنظیمات داخلی LiteSpeed را ویرایش نمی‌کند و به کلاس private آن وابسته نیست.

### WooCommerce، Elementor و WoodMart

Adapterها defensive و اختیاری هستند. WooCommerce contextهای cart، checkout، account، session و customer cookie را برای جلوگیری از cache leakage علامت‌گذاری می‌کند. Adapterهای Elementor و WoodMart شواهد component را استخراج می‌کنند؛ نبود یا تغییر plugin خارجی باید به no-op منجر شود، نه fatal error.

## ۱۱. پایش عملکرد و گزارش‌دهی

Performance Monitor به‌صورت opt-in و sampled کار می‌کند. داده‌های ذخیره‌شده شامل زمان backend/PHP، نوع صفحه، template، cache state و componentهای کم‌کاردینالیتی است. URL کامل، query، body، cookie، header، SQL، هویت کاربر و credential ذخیره نمی‌شوند.

عدد backend time معادل TTFB واقعی مرورگر نیست؛ برای سنجش Core Web Vitals یا latency شبکه باید از ابزار RUM/APM مستقل استفاده شود. Dashboard داده‌های cache hit، اندازهٔ کش، میانگین backend، وضعیت warmup و شمارنده‌های تحلیل را از storageهای موجود خلاصه می‌کند.

## ۱۲. مدل امنیت و حریم خصوصی

- تمام عملیات تغییردهندهٔ admin نیازمند `manage_options` و nonce معتبر است؛
- ورودی در مرز ورود sanitize/allowlist و خروجی متناسب با context escape می‌شود؛
- در نسخهٔ فعلی REST route اختصاصی و SQL مستقیم وجود ندارد؛
- cache key و نام فایل از path خام request ساخته نمی‌شود و hash‌شده است؛
- recordها با `unserialize` خوانده نمی‌شوند و در خرابی hash کنار گذاشته می‌شوند؛
- مسیرهای نوشتن ثابت و متعلق به NPE هستند و executable extensionها محافظت می‌شوند؛
- logger تنها با opt-in و `WP_DEBUG` فعال است و کلیدها/مقادیر حساس را redact می‌کند؛
- jobهای پس‌زمینه محدود، idempotent و دارای حفاظت concurrency هستند؛
- full HTML، credential و دادهٔ شخصی جزو مانیفست‌های تحلیلی نیستند.

فایل `.htaccess` روی Nginx مؤثر نیست؛ بنابراین مدیر سرور باید direct access به مسیر کش NPE را در پیکربندی Nginx نیز مسدود کند. guard داخل فایل یک لایهٔ دفاعی قابل حمل است، نه جایگزین تنظیم درست وب‌سرور.

## ۱۳. رابط مدیریت، فارسی و دسترس‌پذیری

رابط مدیریت از menu، notice، form control، Dashicons و الگوی tab بومی WordPress استفاده می‌کند. assetها فقط در screenهای NPE enqueue می‌شوند. ویژگی‌های اصلی عبارت‌اند از:

- ترجمهٔ کامل رشته‌های رابط با text domain افزونه؛
- فارسی RTL و انگلیسی LTR؛
- نمایش LTR برای URL، hash، path و شناسه‌های فنی؛
- tab قابل استفاده با keyboard و ARIA؛
- status همراه با متن و icon، نه فقط رنگ؛
- layout واکنش‌گرا برای نمایشگر کوچک؛
- progressive enhancement: فرم تنظیمات بدون JavaScript نیز قابل استفاده است.

## ۱۴. رفتار خطا و بازیابی

| خرابی | رفتار مورد انتظار |
| --- | --- |
| upload/cache directory غیرقابل نوشتن | توقف ساخت artifact و ادامه با منابع/صفحهٔ اصلی |
| object cache در دسترس نیست | fallback به filesystem در صورت امن و قابل نوشتن بودن |
| پاسخ تحلیل بزرگ، timeout یا نامعتبر است | ثبت failure محدود و حفظ نسخهٔ قبلی manifest |
| dependency cycle یا script نامطمئن است | کنارگذاشتن از bundle و حفظ handle اصلی |
| cache record خراب یا دست‌کاری‌شده است | cache miss و تولید عادی WordPress |
| integration خارجی نصب نیست | no-op بدون fatal error |
| WP-Cron اجرا نمی‌شود | صف حفظ می‌شود؛ پردازش تا cron واقعی به تعویق می‌افتد |

برای rollback می‌توان ابتدا قابلیت‌های runtime را خاموش، کش NPE را purge، افزونه را غیرفعال و نسخهٔ قبلی را جایگزین کرد. داده‌ها در deactivation حذف نمی‌شوند.

## ۱۵. وضعیت واقعی قابلیت‌ها

| قابلیت | وضعیت نسخهٔ فعلی |
| --- | --- |
| DOM manifest و component detection | عملیاتی |
| یادگیری تدریجی DOM و صف background | عملیاتی و opt-in |
| progress، ETA و purge-aware rescan | عملیاتی |
| تحلیل CSS selector | عملیاتی برای گزارش؛ حذف خودکار عمومی غیرفعال |
| کشف و انتخاب صفحه‌محور فونت | عملیاتی و محافظه‌کارانه |
| JavaScript dependency planning | عملیاتی |
| ساخت artifact و اعمال defer تأییدشده | عملیاتی و opt-in؛ جایگزینی خودکار bundle غیرفعال |
| page/fragment cache | عملیاتی و پیش‌فرض خاموش |
| smart purge و warmup | عملیاتی |
| LiteSpeed public-hook cooperation | عملیاتی و اختیاری |
| WooCommerce/Elementor/WoodMart intelligence | adapterهای اختیاری و defensive |
| پایش backend | عملیاتی، sampled و opt-in |
| CSS critical rendering path مبتنی بر browser | پیاده‌سازی نشده |
| crawler مرورگر و مشاهدهٔ runtime state | پیاده‌سازی نشده |
| پاسخ مستقیم پیش از ورود درخواست به PHP/WordPress | نیازمند کش وب‌سرور یا CDN و خارج از دامنهٔ drop-in وردپرس |

## ۱۶. توسعه، آزمون و ارزیابی

دستورهای مرجع توسعه:

```bash
composer validate --strict
composer test
composer lint
composer check-text
composer build-php-translations
composer build-translations
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Performance/benchmark.php
```

تست‌ها مرزهای cache privacy، صف‌ها، DOM/CSS/font analysis، JavaScript planning، integrationها، admin security، localization و lifecycle را پوشش می‌دهند. micro-benchmark برای مقایسهٔ تکرارپذیر مسیرهای داخلی است و تضمین latency در production محسوب نمی‌شود.

## ۱۷. مسیر مطالعهٔ بیشتر

- [معماری تفصیلی](architecture.md)
- [مدل امنیت و تهدید](security.md)
- [DOM Intelligence](dom-intelligence.md)
- [CSS و فونت](css.md)
- [JavaScript Intelligence](javascript-intelligence.md)
- [کش کامل صفحه](page-cache.md)
- [تشخیص پویا و کش زودهنگام](early-page-cache.md)
- [موتور بهینه‌سازی پویای frontend](frontend-optimization.md)
- [Fragment/Object Cache](fragment-object-cache.md)
- [Smart Purge و Warmup](smart-purge-warmup.md)
- [یکپارچه‌سازی LiteSpeed](litespeed-integration.md)
- [رابط مدیریت](admin-interface.md)
- [گزارش آمادگی انتشار](release-readiness.md)

این سند دامنهٔ نسخهٔ فعلی را توصیف می‌کند؛ وجود کلاس یا برنامهٔ تحلیلی به‌تنهایی به معنی فعال‌بودن تغییر frontend نیست. مرجع نهایی رفتار اجرایی، feature flagهای نسخه، تنظیمات ذخیره‌شده و کد همان release است.
