<x-layouts.site
    :page="$page"
    :calendar-data="$calendarData"
    :description="$description"
    :structured-data="$structuredData"
    :settings="$settings"
    :setting-media="$settingMedia"
    :navigation="$navigation"
    :footer-navigation="$footerNavigation"
    :social-links="$socialLinks"
>
    <div class="admin-alert" style="position:fixed;z-index:1000;right:1rem;bottom:1rem">Draft preview. This link expires automatically.</div>
    <main id="site-main">{!! $content !!}</main>
</x-layouts.site>
