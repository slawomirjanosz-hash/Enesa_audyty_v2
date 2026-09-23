(() => {
    document.querySelectorAll('.plant-nav, .review-section-nav').forEach(nav => {
        const entries = [...nav.querySelectorAll('a[href^="#"]')].map(link => ({
            link, section: document.getElementById(link.hash.slice(1)),
        })).filter(entry => entry.section);
        if (!entries.length) return;
        let active = null;
        let frame = null;
        function update() {
            frame = null;
            const visible = entries.filter(entry => entry.section.getClientRects().length);
            if (!visible.length) return;
            const navBox = nav.getBoundingClientRect();
            const top = Math.max(0, parseFloat(getComputedStyle(nav).top) || 0);
            const offset = top + navBox.height + 16;
            entries.forEach(entry => entry.section.style.scrollMarginTop = `${offset}px`);
            const line = Math.max(offset, navBox.bottom + 16);
            let current = visible[0];
            visible.forEach(entry => { if (entry.section.getBoundingClientRect().top <= line + 1) current = entry; });
            const last = visible[visible.length - 1];
            // A short final section cannot always reach the top of the viewport.
            if (window.scrollY > 0 && window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2
                && last.section.getBoundingClientRect().top < window.innerHeight) current = last;
            if (current === active) return;
            active = current;
            entries.forEach(({link}) => {
                const selected = link === active.link;
                link.classList.toggle('questionnaire-section-active', selected);
                if (selected) link.setAttribute('aria-current', 'location');
                else link.removeAttribute('aria-current');
            });
            // Keep the selected item visible in the mobile horizontal menu only.
            const button = active.link.getBoundingClientRect();
            if (button.left < navBox.left) nav.scrollLeft -= navBox.left - button.left + 8;
            else if (button.right > navBox.right) nav.scrollLeft += button.right - navBox.right + 8;
        }
        const schedule = () => { if (frame === null) frame = requestAnimationFrame(update); };
        window.addEventListener('scroll', schedule, {passive: true});
        window.addEventListener('resize', schedule, {passive: true});
        window.addEventListener('hashchange', schedule);
        window.addEventListener('pageshow', schedule);
        if (window.ResizeObserver) {
            const observer = new ResizeObserver(schedule);
            observer.observe(nav);
            entries.forEach(entry => observer.observe(entry.section));
        }
        update();
    });
})();
