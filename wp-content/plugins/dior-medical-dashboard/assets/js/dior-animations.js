document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Lenis for Smooth Scrolling
    if (typeof Lenis !== 'undefined') {
        const lenis = new Lenis({
            duration: 1.2,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            direction: 'vertical',
            gestureDirection: 'vertical',
            smooth: true,
            mouseMultiplier: 1,
            smoothTouch: false,
            touchMultiplier: 2,
            infinite: false,
        });

        function raf(time) {
            lenis.raf(time);
            requestAnimationFrame(raf);
        }

        requestAnimationFrame(raf);
    }

    // 2. Initialize GSAP animations
    if (typeof gsap !== 'undefined') {
        // Animate tabs and their contents when they become active
        const animateTabContent = (tabId) => {
            const tabContent = document.getElementById(tabId);
            if (!tabContent) return;
            
            // Stagger animation for cards inside the tab
            const cards = tabContent.querySelectorAll('.card, .dior-tab-panel > div');
            if (cards.length > 0) {
                gsap.fromTo(cards, 
                    { y: 30, opacity: 0 }, 
                    { y: 0, opacity: 1, duration: 0.6, stagger: 0.05, ease: "power2.out", clearProps: "all" }
                );
            }
        };

        // Hook into tab changes (if using custom tab logic or bootstrap)
        const tabLinks = document.querySelectorAll('[data-tab], [data-bs-toggle="tab"]');
        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                let targetId = link.getAttribute('data-tab') || link.getAttribute('href');
                if (targetId && targetId.startsWith('#')) {
                    targetId = targetId.substring(1);
                }
                if (targetId) {
                    setTimeout(() => animateTabContent(targetId), 50); // slight delay to allow display:block
                }
            });
        });

        // Initial load animation for the currently active tab
        const activeTab = document.querySelector('.dior-tab-panel.active') || document.querySelector('.dior-tab-panel');
        if (activeTab && activeTab.id) {
            animateTabContent(activeTab.id);
        } else {
            // General fallback animation if no tabs structure found
            gsap.fromTo(".card", 
                { y: 30, opacity: 0 }, 
                { y: 0, opacity: 1, duration: 0.8, stagger: 0.1, ease: "power2.out", clearProps: "all" }
            );
        }
    }
});
