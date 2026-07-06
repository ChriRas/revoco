{{--
    Sticky rich-editor toolbar (slice-024). Long legal texts push the formatting
    toolbar out of view; this keeps it pinned so the operator never scrolls back up
    to reach it. Injected panel-wide via the HEAD_END render hook (registered in
    AppServiceProvider, NOT the panel provider, to avoid colliding with slice-022).

    Scoped to the RichEditor's MAIN toolbar (.fi-fo-rich-editor-toolbar) — the
    floating selection toolbar (.fi-fo-rich-editor-floating-toolbar) is a different
    class and is left untouched. Colours use Filament's own --gray-* theme variables.
--}}
<style>
    .fi-fo-rich-editor .fi-fo-rich-editor-toolbar {
        position: sticky;
        /* Sit just below Filament's own sticky topbar (h-16 = 4rem). */
        top: 4rem;
        z-index: 10;
        /* Opaque so scrolled content does not show through the toolbar. */
        background-color: var(--gray-50, #ffffff);
    }

    .dark .fi-fo-rich-editor .fi-fo-rich-editor-toolbar,
    [data-theme='dark'] .fi-fo-rich-editor .fi-fo-rich-editor-toolbar {
        background-color: var(--gray-900, #18181b);
    }
</style>
