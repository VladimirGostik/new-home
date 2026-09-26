import { onMounted, onUnmounted, ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';

export function useDropdown(root: Ref<HTMLElement | null>) {
    const isOpen = ref(false);

    function open() {
        isOpen.value = true;
    }

    function close() {
        isOpen.value = false;
    }

    function toggle() {
        isOpen.value = !isOpen.value;
    }

    function handlePointerDown(event: PointerEvent) {
        if (!isOpen.value) return;
        const target = event.target as Node | null;
        if (root.value && target && !root.value.contains(target)) {
            close();
        }
    }

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            close();
        }
    }

    let stopRouterListener: (() => void) | null = null;

    onMounted(() => {
        document.addEventListener('pointerdown', handlePointerDown, true);
        document.addEventListener('keydown', handleKeydown);
        stopRouterListener = router.on('start', close);
    });

    onUnmounted(() => {
        document.removeEventListener('pointerdown', handlePointerDown, true);
        document.removeEventListener('keydown', handleKeydown);
        stopRouterListener?.();
    });

    return { isOpen, open, close, toggle };
}
