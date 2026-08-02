<dialog
    class="app-confirm-dialog"
    data-confirm-dialog
    aria-labelledby="app-confirm-title"
    aria-describedby="app-confirm-message"
>
    <div class="app-confirm-panel">
        <div class="app-confirm-header">
            <span class="app-confirm-icon" aria-hidden="true">
                <img src="{{ asset('assets/icons/warning.svg') }}" alt="">
            </span>
            <div>
                <p class="app-confirm-eyebrow">Confirmation required</p>
                <h2 id="app-confirm-title" data-confirm-title>Confirm action</h2>
            </div>
            <button class="app-confirm-close" type="button" data-confirm-cancel aria-label="Close confirmation">&times;</button>
        </div>

        <p id="app-confirm-message" class="app-confirm-message" data-confirm-message></p>

        <div class="app-confirm-prompt" data-confirm-prompt hidden>
            <label for="app-confirm-input">
                Type <strong data-confirm-phrase></strong> to continue
            </label>
            <input
                id="app-confirm-input"
                type="text"
                autocomplete="off"
                spellcheck="false"
                data-confirm-input
            >
            <span class="app-confirm-validation" data-confirm-validation aria-live="polite"></span>
        </div>

        <div class="app-confirm-actions">
            <button class="app-confirm-button secondary" type="button" data-confirm-cancel>Cancel</button>
            <button class="app-confirm-button danger" type="button" data-confirm-accept>Confirm</button>
        </div>
    </div>
</dialog>
