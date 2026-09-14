<aside id="notifications" data-url="{{ route('interpresso.notifications') }}" data-read-all-url="{{ route('interpresso.notifications.read-all') }}" class="fixed bottom-4 right-4 z-40">
    <button id="notification-toggle" type="button" data-toggle="notification-list" aria-controls="notification-list" aria-expanded="false" class="btn btn-primary"><span>{{ __('interpresso::global.notifications') }} (<span data-count>0</span>)</span></button>
    <div id="notification-list" hidden role="region" aria-labelledby="notification-toggle" class="absolute bottom-full right-0 bg-base-100 rounded-box shadow-lg p-4 w-80 max-w-[calc(100vw-2rem)] max-h-80 overflow-y-auto mb-2">
        <button type="button" data-read-all class="btn btn-ghost btn-sm text-primary">{{ __('interpresso::global.mark_all_read') }}</button>
        <ul data-notification-items class="space-y-3"></ul>
    </div>
</aside>
