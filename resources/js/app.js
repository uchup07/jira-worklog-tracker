import ApexCharts from 'apexcharts';

window.appTheme = {
    lastTheme: null,

    sync(theme) {
        if (this.lastTheme === theme) {
            return;
        }

        this.lastTheme = theme;
        this.persist(theme);
    },

    async persist(mode) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        if (! token) {
            return;
        }

        try {
            await fetch('/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ theme: mode }),
            });
        } catch (error) {
            console.error('Failed to persist theme preference.', error);
        }
    },
};

window.ApexCharts = ApexCharts;
