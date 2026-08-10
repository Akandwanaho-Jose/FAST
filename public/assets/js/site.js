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

    const desktopQuery = window.matchMedia('(min-width: 1101px)');
    desktopQuery.addEventListener('change', (event) => {
        if (event.matches) {
            setMenuOpen(false);
        }
    });

    const revealSections = document.querySelectorAll('[data-study-reveal], [data-why-reveal], [data-about-reveal], [data-programme-reveal], [data-department-reveal], [data-staff-reveal], [data-feature-reveal]');
    revealSections.forEach((section) => {
        if (!(section instanceof HTMLElement)) {
            return;
        }
        if (!('IntersectionObserver' in window)) {
            section.classList.add('is-revealed');
            return;
        }
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    section.classList.add('is-revealed');
                    return;
                }

                section.classList.add('is-resetting');
                section.classList.remove('is-revealed');
                void section.offsetWidth;
                window.requestAnimationFrame(() => {
                    section.classList.remove('is-resetting');
                });
            });
        }, { threshold: 0.12, rootMargin: '-6% 0px -6% 0px' });
        observer.observe(section);
    });

    const whyGallery = document.querySelector('[data-why-gallery]');
    if (whyGallery instanceof HTMLElement) {
        const galleryItems = Array.from(whyGallery.querySelectorAll('[data-why-gallery-item]'));
        const galleryReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const galleryVisibleCount = Math.min(3, galleryItems.length);
        let galleryStart = 0;
        let galleryTimer = null;
        let galleryPaused = false;
        let gallerySwitching = false;

        const renderGallery = () => {
            galleryItems.forEach((item) => {
                item.hidden = true;
                item.removeAttribute('data-slot');
            });
            for (let slot = 0; slot < galleryVisibleCount; slot += 1) {
                const itemIndex = (galleryStart + slot) % galleryItems.length;
                const item = galleryItems[itemIndex];
                item.hidden = false;
                item.setAttribute('data-slot', String(slot + 1));
            }
        };

        const stopGallery = () => {
            if (galleryTimer !== null) {
                window.clearInterval(galleryTimer);
                galleryTimer = null;
            }
        };

        const startGallery = () => {
            stopGallery();
            if (galleryItems.length <= galleryVisibleCount || galleryPaused
                || galleryReducedMotion.matches || document.hidden
            ) {
                return;
            }
            galleryTimer = window.setInterval(() => moveGallery(galleryVisibleCount), 10000);
        };

        const moveGallery = (offset) => {
            if (gallerySwitching || galleryItems.length <= galleryVisibleCount) {
                return;
            }
            gallerySwitching = true;
            whyGallery.classList.add('is-switching');
            window.setTimeout(() => {
                galleryStart = (galleryStart + offset + galleryItems.length) % galleryItems.length;
                renderGallery();
                window.requestAnimationFrame(() => {
                    whyGallery.classList.remove('is-switching');
                    gallerySwitching = false;
                    startGallery();
                });
            }, 950);
        };
        whyGallery.addEventListener('mouseenter', () => {
            galleryPaused = true;
            stopGallery();
        });
        whyGallery.addEventListener('mouseleave', () => {
            galleryPaused = false;
            startGallery();
        });
        whyGallery.addEventListener('focusin', () => {
            galleryPaused = true;
            stopGallery();
        });
        whyGallery.addEventListener('focusout', (event) => {
            if (event.relatedTarget instanceof Node && whyGallery.contains(event.relatedTarget)) {
                return;
            }
            galleryPaused = false;
            startGallery();
        });
        galleryReducedMotion.addEventListener('change', startGallery);
        document.addEventListener('visibilitychange', startGallery);

        renderGallery();
        startGallery();
    }

    const staffPortraitGallery = document.querySelector('[data-staff-portrait-gallery]');
    if (staffPortraitGallery instanceof HTMLElement) {
        const portraitItems = Array.from(staffPortraitGallery.querySelectorAll('[data-staff-portrait-item]'));
        const portraitReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const portraitVisibleCount = Math.min(3, portraitItems.length);
        let portraitTimer = null;
        let portraitPaused = false;
        let portraitSwitching = false;

        const shufflePortraits = (items) => {
            const shuffled = [...items];
            for (let index = shuffled.length - 1; index > 0; index -= 1) {
                const randomIndex = Math.floor(Math.random() * (index + 1));
                [shuffled[index], shuffled[randomIndex]] = [shuffled[randomIndex], shuffled[index]];
            }
            return shuffled;
        };

        const stopPortraits = () => {
            if (portraitTimer !== null) {
                window.clearInterval(portraitTimer);
                portraitTimer = null;
            }
        };

        const startPortraits = () => {
            stopPortraits();
            if (portraitPaused || portraitReducedMotion.matches || document.hidden
                || portraitItems.length <= portraitVisibleCount) {
                return;
            }
            portraitTimer = window.setInterval(showNextPortraits, 7000);
        };

        function showNextPortraits() {
            if (portraitSwitching) {
                return;
            }
            const currentItems = portraitItems.filter((item) => !item.hidden);
            let candidates = portraitItems.filter((item) => item.hidden);
            if (candidates.length < portraitVisibleCount) {
                candidates = [
                    ...shufflePortraits(candidates),
                    ...shufflePortraits(currentItems),
                ];
            }
            const nextItems = shufflePortraits(candidates).slice(0, portraitVisibleCount);
            if (nextItems.length === 0) {
                return;
            }
            portraitSwitching = true;
            staffPortraitGallery.classList.add('is-changing');
            window.setTimeout(() => {
                portraitItems.forEach((item) => { item.hidden = !nextItems.includes(item); });
                window.requestAnimationFrame(() => {
                    staffPortraitGallery.classList.remove('is-changing');
                    portraitSwitching = false;
                });
            }, 500);
        }

        staffPortraitGallery.addEventListener('mouseenter', () => {
            portraitPaused = true;
            stopPortraits();
        });
        staffPortraitGallery.addEventListener('mouseleave', () => {
            portraitPaused = false;
            startPortraits();
        });
        staffPortraitGallery.addEventListener('focusin', () => {
            portraitPaused = true;
            stopPortraits();
        });
        staffPortraitGallery.addEventListener('focusout', (event) => {
            if (event.relatedTarget instanceof Node && staffPortraitGallery.contains(event.relatedTarget)) {
                return;
            }
            portraitPaused = false;
            startPortraits();
        });
        portraitReducedMotion.addEventListener('change', startPortraits);
        document.addEventListener('visibilitychange', startPortraits);
        startPortraits();
    }

    document.querySelectorAll('[data-profile-tabs]').forEach((tabSet) => {
        const tabs = Array.from(tabSet.querySelectorAll('[data-profile-tab]'));
        const panels = Array.from(tabSet.querySelectorAll('[data-profile-panel]'));
        if (tabs.length === 0 || panels.length === 0) return;

        const activate = (tab, focus = false) => {
            const target = tab.getAttribute('data-profile-tab');
            tabs.forEach((item) => {
                const selected = item === tab;
                item.setAttribute('aria-selected', String(selected));
                item.setAttribute('tabindex', selected ? '0' : '-1');
            });
            panels.forEach((panel) => {
                panel.hidden = panel.getAttribute('data-profile-panel') !== target;
            });
            if (focus) tab.focus();
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab));
            tab.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                let nextIndex = index;
                if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
                if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
                if (event.key === 'Home') nextIndex = 0;
                if (event.key === 'End') nextIndex = tabs.length - 1;
                activate(tabs[nextIndex], true);
            });
        });
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
    const requestedInterval = Number.parseInt(carousel.dataset.carouselInterval ?? '10', 10);
    const intervalMs = Math.max(7000, Math.min(20000, (Number.isFinite(requestedInterval) ? requestedInterval : 10) * 1000));

    if (slides.length < 2) {
        return;
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let activeIndex = 0;
    let timer = null;
    let userPaused = reducedMotion.matches;
    let interactionPaused = false;

    const updatePauseControl = () => {
        if (!(pause instanceof HTMLButtonElement)) {
            return;
        }
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
            }, 1000);
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
        timer = window.setInterval(() => showSlide(activeIndex + 1), intervalMs);
    };

    const move = (offset) => {
        showSlide(activeIndex + offset);
        startTimer();
    };

    previous?.addEventListener('click', () => move(-1));
    next?.addEventListener('click', () => move(1));
    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            const index = Number.parseInt(dot.getAttribute('data-carousel-dot') ?? '', 10);
            if (Number.isInteger(index)) {
                showSlide(index);
                startTimer();
            }
        });
    });
    pause?.addEventListener('click', () => {
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
