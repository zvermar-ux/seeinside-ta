@auth
    @php($user = auth()->user())
    <div class="ta-agency-badge" x-show="$store.sidebar.isOpen">
        <div class="ta-agency-name">{{ $user->agency?->name ?? __('All agencies') }}</div>
        <div class="ta-agency-role">{{ \App\Support\Permissions::roleLabel($user->primaryRole()) }}</div>
    </div>
@endauth
