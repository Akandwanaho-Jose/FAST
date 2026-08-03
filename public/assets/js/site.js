(() => {
    const root = document.documentElement;
    const toggle = document.querySelector('.navigation-toggle');
    const navigation = document.querySelector('.primary-navigation');

    root.classList.add('js');

    if (!(toggle instanceof HTMLButtonElement) || !(navigation instanceof HTMLElement)) {
        return;
    }

    const setMenuOpen = (open) => {
        toggle.setAttribute('aria-expanded', String(open));
        navigation.classList.toggle('is-open', open);
        document.body.classList.toggle('navigation-open', open);

        if (!open) {
            navigation.querySelectorAll('details[open]').forEach((group) => group.removeAttribute('open'));
        }
    };

    toggle.addEventListener('click', () => {
        setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    navigation.querySelectorAll('details').forEach((group) => {
        group.addEventListener('toggle', () => {
            if (!group.open) {
                return;
            }

            navigation.querySelectorAll('details[open]').forEach((other) => {
                if (other !== group) {
                    other.removeAttribute('open');
                }
            });
        });

        let hoverCloseTimer = null;
        group.addEventListener('mouseenter', () => {
            if (!desktopQuery.matches) {
                return;
            }
            window.clearTimeout(hoverCloseTimer);
            group.setAttribute('open', '');
        });
        group.addEventListener('mouseleave', () => {
            if (!desktopQuery.matches) {
                return;
            }
            hoverCloseTimer = window.setTimeout(() => {
                if (!group.contains(document.activeElement)) {
                    group.removeAttribute('open');
                }
            }, 140);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const menuIsOpen = toggle.getAttribute('aria-expanded') === 'true';
            const openGroups = Array.from(navigation.querySelectorAll('details[open]'));

            if (menuIsOpen) {
                setMenuOpen(false);
                toggle.focus();
            } else if (openGroups.length > 0) {
                const lastGroup = openGroups[openGroups.length - 1];
                lastGroup.removeAttribute('open');
                lastGroup.querySelector('summary')?.focus();
            }
        }
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Node) || navigation.contains(event.target) || toggle.contains(event.target)) {
            return;
        }

        if (toggle.getAttribute('aria-expanded') === 'true') {
            setMenuOpen(false);
        } else {
            navigation.querySelectorAll('details[open]').forEach((group) => group.removeAttribute('open'));
        }
    });

    const desktopQuery = window.matchMedia('(min-width: 961px)');
    desktopQuery.addEventListener('change', (event) => {
        if (event.matches) {
            setMenuOpen(false);
        }
    });

    const carousel = document.querySelector('[data-carousel]');
    if (!(carousel instanceof HTMLElement)) {
        return;
    }

    const slides = Array.from(carousel.querySelectorAll('[data-carousel-slide]'));
    const previous = carousel.querySelector('[data-carousel-previous]');
    const next = carousel.querySelector('[data-carousel-next]');
    const pause = carousel.querySelector('[data-carousel-pause]');
    const pauseLabel = carousel.querySelector('[data-carousel-pause-label]');
    const pauseIcon = pause?.querySelector('[aria-hidden="true"]');
    const dots = Array.from(carousel.querySelectorAll('[data-carousel-dot]'));

    if (slides.length < 2 || !(previous instanceof HTMLButtonElement)
        || !(next instanceof HTMLButtonElement) || !(pause instanceof HTMLButtonElement)
    ) {
        return;
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let activeIndex = 0;
    let timer = null;
    let userPaused = reducedMotion.matches;
    let interactionPaused = false;

    const updatePauseControl = () => {
        pause.setAttribute('aria-pressed', String(userPaused));
        if (pauseLabel) {
            pauseLabel.textContent = userPaused ? 'Play slides' : 'Pause slides';
        }
        if (pauseIcon) {
            pauseIcon.textContent = userPaused ? '▶' : 'Ⅱ';
        }
    };

    const stopTimer = () => {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    const showSlide = (index) => {
        const nextIndex = (index + slides.length) % slides.length;
        const previousIndex = activeIndex;
        activeIndex = nextIndex;

        const incoming = slides[nextIndex];
        incoming.hidden = false;
        incoming.setAttribute('aria-hidden', 'false');
        window.requestAnimationFrame(() => incoming.classList.add('is-active'));

        if (previousIndex !== nextIndex) {
            const outgoing = slides[previousIndex];
            outgoing.classList.remove('is-active');
            outgoing.setAttribute('aria-hidden', 'true');
            window.setTimeout(() => {
                if (!outgoing.classList.contains('is-active')) {
                    outgoing.hidden = true;
                }
            }, 650);
        }

        dots.forEach((dot, dotIndex) => {
            dot.setAttribute('aria-pressed', String(dotIndex === activeIndex));
        });
    };

    const startTimer = () => {
        stopTimer();
        if (userPaused || interactionPaused || reducedMotion.matches || document.hidden) {
            return;
        }
        timer = window.setInterval(() => showSlide(activeIndex + 1), 8000);
    };

    const move = (offset) => {
        showSlide(activeIndex + offset);
        startTimer();
    };

    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            const index = Number.parseInt(dot.getAttribute('data-carousel-dot') ?? '', 10);
            if (Number.isInteger(index)) {
                showSlide(index);
                startTimer();
            }
        });
    });
    pause.addEventListener('click', () => {
        userPaused = !userPaused;
        updatePauseControl();
        startTimer();
    });
    carousel.addEventListener('mouseenter', () => {
        interactionPaused = true;
        stopTimer();
    });
    carousel.addEventListener('mouseleave', () => {
        interactionPaused = false;
        startTimer();
    });
    carousel.addEventListener('focusin', () => {
        interactionPaused = true;
        stopTimer();
    });
    carousel.addEventListener('focusout', (event) => {
        if (event.relatedTarget instanceof Node && carousel.contains(event.relatedTarget)) {
            return;
        }
        interactionPaused = false;
        startTimer();
    });
    reducedMotion.addEventListener('change', (event) => {
        if (event.matches) {
            userPaused = true;
            updatePauseControl();
            stopTimer();
        }
    });
    document.addEventListener('visibilitychange', startTimer);

    updatePauseControl();
    showSlide(0);
    startTimer();
})();
