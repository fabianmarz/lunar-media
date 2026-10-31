import type { Component } from 'vue';

declare global {
    interface Window {
        LunarPanel: {
            registerPages(pages: Record<string, Component>): void;
            registerComponents(namespace: string, components: Record<string, Component>): void;
        };
    }
}

export {};
