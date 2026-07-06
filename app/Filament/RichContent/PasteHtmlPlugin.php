<?php

declare(strict_types=1);

namespace App\Filament\RichContent;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Str;
use Tiptap\Core\Extension;

/**
 * Adds a "paste HTML" toolbar button to a RichEditor. It opens a modal with a
 * plain textarea; on submit the markup is sanitized with Str::sanitizeHtml() (the
 * same allow-list the public legal pages render through — stored-XSS defense) and
 * inserted at the cursor as formatted editor content. Lets an operator drop in a
 * law firm's HTML privacy policy without fighting the rich editor by hand.
 *
 * Insert (not replace): non-destructive, and it fills an empty editor naturally.
 */
final class PasteHtmlPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    /**
     * Sanitize pasted markup to the editor/legal-page allow-list. Non-string or
     * empty input yields an empty string. Exposed for direct testing.
     */
    public static function sanitize(mixed $raw): string
    {
        $html = is_string($raw) ? trim($raw) : '';

        return $html === '' ? '' : Str::sanitizeHtml($html);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('pasteHtml')
                ->label(__('panel.editor.paste_html.tool'))
                ->icon('heroicon-o-code-bracket')
                ->action(),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            Action::make('pasteHtml')
                ->modalHeading(__('panel.editor.paste_html.heading'))
                ->modalDescription(__('panel.editor.paste_html.description'))
                ->modalSubmitActionLabel(__('panel.editor.paste_html.submit'))
                ->schema([
                    Textarea::make('html')
                        ->hiddenLabel()
                        ->placeholder(__('panel.editor.paste_html.placeholder'))
                        ->rows(14)
                        ->required(),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $html = self::sanitize($data['html'] ?? null);

                    if ($html === '') {
                        return;
                    }

                    $rawSelection = $arguments['editorSelection'] ?? null;
                    /** @var array<string, mixed>|null $selection */
                    $selection = is_array($rawSelection) ? $rawSelection : null;

                    $component->runCommands(
                        [EditorCommand::make('insertContent', [$html])],
                        editorSelection: $selection,
                    );
                }),
        ];
    }
}
