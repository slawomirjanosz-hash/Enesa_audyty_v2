const offerSectionCollapse = {
    mount(card, collapsed = false) {
        const header = card.querySelector(':scope > .ed-card-header');
        let button = header.querySelector('[data-section-collapse]');
        if (!button) {
            button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn-section-action';
            button.dataset.sectionCollapse = '';
            button.innerHTML = '<i class="ti" aria-hidden="true"></i>';
            header.querySelector('.section-name-input').after(button);
            button.addEventListener('click', () => {
                this.mount(card, !this.isCollapsed(card));
                card.dispatchEvent(new Event('change', {bubbles: true}));
            });
        }
        collapsed = collapsed === true;
        card.classList.toggle('offer-section-collapsed', collapsed);
        button.setAttribute('aria-expanded', String(!collapsed));
        button.title = collapsed ? 'Rozwiń sekcję' : 'Zwiń sekcję';
        button.setAttribute('aria-label', button.title);
        button.firstElementChild.className = collapsed ? 'ti ti-chevron-down' : 'ti ti-chevron-up';
    },

    isCollapsed(card) {
        return card.classList.contains('offer-section-collapsed');
    },
};
