/**
 * Scroll-reveal composable using IntersectionObserver.
 * Adds 'is-visible' class when element enters viewport.
 * Usage: const el = useReveal() → <div :ref="el">
 */
export function useReveal(options?: { threshold?: number; rootMargin?: string }) {
  const target = ref<HTMLElement | null>(null)

  onMounted(() => {
    if (!target.value || typeof IntersectionObserver === 'undefined') return

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible')
            observer.unobserve(entry.target)
          }
        })
      },
      {
        threshold: options?.threshold ?? 0.15,
        rootMargin: options?.rootMargin ?? '0px 0px -40px 0px',
      }
    )
    observer.observe(target.value)

    onUnmounted(() => observer.disconnect())
  })

  return target
}

/**
 * Observe multiple children of a container for staggered reveals.
 * Each child with [data-reveal] gets 'is-visible' class with stagger delay.
 */
export function useStaggerReveal(options?: { threshold?: number; stagger?: number }) {
  const container = ref<HTMLElement | null>(null)

  onMounted(() => {
    if (!container.value || typeof IntersectionObserver === 'undefined') return

    const staggerMs = options?.stagger ?? 60

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const children = Array.from(
              (entry.target as HTMLElement).querySelectorAll('[data-reveal]')
            )
            children.forEach((child, i) => {
              setTimeout(() => {
                child.classList.add('is-visible')
              }, i * staggerMs)
            })
            observer.unobserve(entry.target)
          }
        })
      },
      { threshold: options?.threshold ?? 0.1 }
    )
    observer.observe(container.value)

    onUnmounted(() => observer.disconnect())
  })

  return container
}
