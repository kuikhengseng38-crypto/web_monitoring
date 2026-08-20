(function () {
    var seconds = parseInt(document.body.getAttribute('data-refresh') || '30', 10);
    var left = seconds;

    function pillClass(status) {
        return 'pill pill-' + String(status || 'unknown').toLowerCase();
    }

    function render(data) {
        var hero = document.getElementById('hero');
        hero.className = 'hero hero-' + data.overall;
        document.getElementById('overall-label').textContent = data.overall_label;
        document.getElementById('updated').textContent = data.updated;
        document.getElementById('counts').textContent = data.counts.up + ' UP · ' + data.counts.down + ' DOWN';

        var html = '';
        if (!data.services.length) {
            html = '<p class="empty">No websites are being monitored yet.</p>';
        } else {
            data.services.forEach(function (service) {
                var bars = service.history.map(function (bar) {
                    return '<i class="bar bar-' + bar.state + '" title="' + bar.date + ' ' + bar.state + '"></i>';
                }).join('');
                var uptime = service.uptime_24h === null ? '—' : service.uptime_24h + '%';
                var checked = service.last_checked || 'Never';
                var http = service.http_code ? ' · HTTP ' + service.http_code : '';
                html += '<article class="service">'
                    + '<div class="service-row"><div>'
                    + '<h3>' + escapeHtml(service.name) + '</h3>'
                    + '<a class="service-url" href="' + escapeHtml(service.url) + '" target="_blank" rel="noopener">' + escapeHtml(service.url) + '</a>'
                    + '</div><span class="' + pillClass(service.status) + '">' + escapeHtml(service.status) + '</span></div>'
                    + '<div class="bars" title="Last ' + (data.history_days || 90) + ' days">' + bars + '</div>'
                    + '<p class="service-meta">24h uptime: ' + uptime + ' · Last check: ' + escapeHtml(checked) + http + '</p>'
                    + '</article>';
            });
        }
        document.getElementById('services').innerHTML = html;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function tick() {
        left -= 1;
        if (left <= 0) {
            left = seconds;
            fetch('status_api.php', { cache: 'no-store' })
                .then(function (response) { return response.json(); })
                .then(render)
                .catch(function () {});
        }
        var countdown = document.getElementById('countdown');
        if (countdown) {
            countdown.textContent = String(left);
        }
    }

    setInterval(tick, 1000);
})();
