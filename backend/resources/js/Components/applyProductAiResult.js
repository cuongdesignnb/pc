// Copy an AI product-page result into the product form (create and edit).
// Only the sections the administrator ticked come back filled, so empty
// parts never wipe what is already in the form. Nothing is saved here.
export function applyProductAiResult(form, data) {
    const sections = data.sections || ['description'];

    if (sections.includes('description')) {
        form.description = data.content || data.body || form.description;
        form.short_description = data.short_description || data.excerpt || form.short_description;
        form.meta_title = data.meta_title || form.meta_title;
        form.meta_description = data.meta_description || form.meta_description;
    }
    if (data.thumbnail) form.thumbnail = data.thumbnail;

    if (sections.includes('specifications') && data.specifications_text) {
        form.specifications_text = data.specifications_text;
    }
    if (sections.includes('highlights') && data.highlights?.length) {
        form.highlights = data.highlights.map(title => ({ title, icon: '', is_active: true }));
    }
    if (sections.includes('blocks') && data.detail_blocks?.length) {
        // Keep blocks the AI does not write (banner, image + text, notice,
        // contact footer) and replace only the card blocks it generated.
        const generatedTypes = data.detail_blocks.map(block => block.type);
        form.detail_blocks = [
            ...form.detail_blocks.filter(block => !generatedTypes.includes(block.type)),
            ...data.detail_blocks.map(block => ({
                type: block.type,
                title: block.title || '',
                payload: { cards: (block.payload?.cards || []).map(card => ({ ...card })) },
                is_active: true,
            })),
        ];
    }
}
