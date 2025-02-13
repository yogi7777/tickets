// Diese Datei als assets/js/dark-mode.js speichern
class DarkMode {
    constructor() {
        this.theme = this.getTheme();
        this.systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
        this.init();
    }

    init() {
        // Initial Theme setzen
        this.applyTheme(this.theme);
        
        // System Theme Change Listener
        this.systemTheme.addEventListener('change', (e) => {
            if (this.theme === 'auto') {
                this.applyTheme('auto');
            }
        });
    }

    getTheme() {
        // Cookie oder Auto-Modus
        return document.cookie.split('; ').find(row => row.startsWith('theme='))?.split('=')[1] || 'auto';
    }

    setTheme(theme) {
        this.theme = theme;
        document.cookie = `theme=${theme};path=/;max-age=31536000`; // 1 Jahr
        this.applyTheme(theme);
    }

    applyTheme(theme) {
        let effectiveTheme = theme;
        
        if (theme === 'auto') {
            effectiveTheme = this.systemTheme.matches ? 'dark' : 'light';
        }
        
        document.documentElement.setAttribute('data-bs-theme', effectiveTheme);
        this.updateToggleButton(theme);
    }

    updateToggleButton(theme) {
        const button = document.getElementById('theme-toggle');
        if (button) {
            button.innerHTML = this.getToggleButtonContent(theme);
        }
    }

    getToggleButtonContent(theme) {
        const icons = {
            light: '☀️',
            dark: '🌙',
            auto: '⚙️'
        };
        return icons[theme] || '⚙️';
    }

    toggleTheme() {
        const themes = ['light', 'dark', 'auto'];
        const currentIndex = themes.indexOf(this.theme);
        const nextIndex = (currentIndex + 1) % themes.length;
        this.setTheme(themes[nextIndex]);
    }
}

// Initialisierung
document.addEventListener('DOMContentLoaded', () => {
    window.darkMode = new DarkMode();
});
