export function sectionExtras(data) {
    return {
        rooms: data.retrieved_rooms?.length ? data.retrieved_rooms : null,
        hotels: data.retrieved_hotels?.length ? data.retrieved_hotels : null,
        activities: data.retrieved_activities?.length ? data.retrieved_activities : null,
        packages: data.retrieved_packages?.length ? data.retrieved_packages : null,
        addons: data.retrieved_addons?.length ? data.retrieved_addons : null,
        ordering: data.result_ordering, itinerary: data.itinerary, map: data.map,
        location_request: data.location_request, location_target: data.location_target,
    };
}

export function displayChatMessages(messages) {
    return messages.flatMap(message => {
        if (message.sender !== 'bot' || !message.sections?.length) return [message];
        return message.sections.map((section, index) => {
            const last = index === message.sections.length - 1;
            const destination = section.constraints?.destination_name;
            let text = '### ' + section.heading + (destination ? ' — ' + destination : '') + '\n\n' + section.answer;
            if (last) {
                if (message.section_footer) text += '\n\n' + message.section_footer;
                if (message.price_effective_note) text += '\n\n' + message.price_effective_note;
                const nudge = message.text.match(/\n\nYou're chatting as a guest[^\n]*$/);
                if (nudge) text += nudge[0];
            }
            if (index === 0 && message.text.startsWith('⚠️ Account notice:')) {
                text = message.text.split('\n\n')[0] + '\n\n' + text;
            }
            return {
                id: String(message.id) + ':' + section.id, sender: 'bot', text,
                ...sectionExtras(section.data || {}),
                ai_notice: index === 0 ? message.ai_notice : null,
                suggested_actions: last ? message.suggested_actions : [],
            };
        });
    });
}
