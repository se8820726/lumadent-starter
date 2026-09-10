// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';

const source = readFileSync('resources/js/theme.js', 'utf8');

function boot({ stored = null, dark = false, denied = false, controls = true } = {}) {
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.removeAttribute('data-theme-preference');
    document.documentElement.removeAttribute('style');
    document.body.innerHTML = controls ? '<label data-theme-control hidden><select data-theme-select><option value="auto">Auto</option><option value="light">Light</option><option value="dark">Dark</option></select></label>' : '';
    const media = { matches: dark, addEventListener: vi.fn() };
    vi.stubGlobal('matchMedia', vi.fn(() => media));
    const storage = {
        getItem: vi.fn(() => { if (denied) throw new Error('Unavailable'); return stored; }),
        setItem: vi.fn((key, value) => { if (denied) throw new Error('Unavailable'); stored = value; }),
    };
    vi.stubGlobal('localStorage', storage);
    const events = {};
    vi.spyOn(window, 'addEventListener').mockImplementation((name, handler) => { events[name] = handler; });
    vi.spyOn(document, 'addEventListener').mockImplementation((name, handler) => { events[name] = handler; });
    window.eval(source);
    return { media, storage, events, root: document.documentElement };
}

afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); });

describe('appearance', () => {
    it.each([
        [null, false, 'light', 'auto'], [null, true, 'dark', 'auto'],
        ['light', true, 'light', 'light'], ['dark', false, 'dark', 'dark'],
        ['auto', true, 'dark', 'auto'], ['invalid', false, 'light', 'auto'],
    ])('sets the initial theme before the body exists: %s / %s', (stored, dark, theme, preference) => {
        const { root } = boot({ stored, dark, controls: false });
        expect(root.dataset.theme).toBe(theme);
        expect(root.dataset.themePreference).toBe(preference);
        expect(root.style.colorScheme).toBe(theme);
    });

    it('stores the chosen preference and synchronises the control', () => {
        const { events, storage, root } = boot();
        events.DOMContentLoaded();
        const select = document.querySelector('select');
        select.value = 'dark';
        select.dispatchEvent(new Event('change'));
        expect(root.dataset.theme).toBe('dark');
        expect(storage.setItem).toHaveBeenCalledWith('lumadent-theme', 'dark');
        expect(document.querySelector('label').hidden).toBe(false);
        select.value = 'auto';
        select.dispatchEvent(new Event('change'));
        expect(root.dataset.themePreference).toBe('auto');
    });

    it('follows live system changes only in auto mode', () => {
        const { media, root, events } = boot();
        events.DOMContentLoaded();
        media.matches = true;
        media.addEventListener.mock.calls[0][1]();
        expect(root.dataset.theme).toBe('dark');
        const select = document.querySelector('select');
        select.value = 'light';
        select.dispatchEvent(new Event('change'));
        media.addEventListener.mock.calls[0][1]();
        expect(root.dataset.theme).toBe('light');
    });

    it('remains usable when local storage is denied', () => {
        const { root, events } = boot({ denied: true, dark: true });
        expect(root.dataset.theme).toBe('dark');
        events.DOMContentLoaded();
        const select = document.querySelector('select');
        select.value = 'light';
        expect(() => select.dispatchEvent(new Event('change'))).not.toThrow();
        expect(root.dataset.theme).toBe('light');
    });

    it('refreshes the preference after another tab changes it and after back navigation', () => {
        const { root, storage, events } = boot();
        storage.getItem.mockReturnValue('dark');
        events.storage({ key: 'lumadent-theme' });
        expect(root.dataset.theme).toBe('dark');
        storage.getItem.mockReturnValue('light');
        events.pageshow({ persisted: true });
        expect(root.dataset.theme).toBe('light');
        storage.getItem.mockReturnValue(null);
        events.storage({ key: null });
        expect(root.dataset.themePreference).toBe('auto');
    });
});
