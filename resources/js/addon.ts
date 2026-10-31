import IndexPage from './pages/Index.vue';

// Utilities the panel's own build doesn't emit but this page uses.
const styles = `
.w-10{width:2.5rem}.h-10{height:2.5rem}.object-cover{object-fit:cover}
.text-\\[12px\\]{font-size:12px}.text-\\[12\\.5px\\]{font-size:12.5px}
.min-w-0{min-width:0}.hover\\:underline:hover{text-decoration:underline}
`;

const styleEl = document.createElement('style');
styleEl.textContent = styles;
document.head.appendChild(styleEl);

// Registration must be eager: the panel resolves the initial page on
// DOMContentLoaded, so anything deferred to booting() runs too late.
window.LunarPanel.registerPages({
    'media::Media/Index': IndexPage,
});
