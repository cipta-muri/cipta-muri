import React from 'react';
import { createRoot } from 'react-dom/client';
import ChatWidget from './components/ChatWidget';

const mount = () => {
    const container = document.getElementById('global-chat-widget');
    if (!container || container.dataset.initialized === 'true') {
        return;
    }

    container.dataset.initialized = 'true';
    const root = createRoot(container);
    root.render(<ChatWidget />);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
    mount();
}

