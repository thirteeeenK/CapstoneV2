export function sectionExtras(data) {
    return {
        rooms: data.retrieved_rooms?.length ? data.retrieved_rooms : null,
        hotels: data.retrieved_hotels?.length ? data.retrieved_hotels : null,
        activities: data.retrieved_activities?.length ? data.retrieved_activities : null,
        packages: data.retrieved_packages?.length ? data.retrieved_packages : null,
        addons: data.retrieved_addons?.length ? data.retrieved_addons : null,
        ordering: data.result_ordering, itinerary: data.itinerary, itinerary_suggestion: data.itinerary_suggestion, map: data.map,
        location_request: data.location_request, location_target: data.location_target,
    };
}

export function displayChatMessages(messages) {
    const latestBot = messages.findLast(message => message.sender === 'bot');
    const actions = message => (message.suggested_actions || []).map(action => action.question_id ? ({
        ...action, stale: message !== latestBot || action.question_id !== latestBot?.clarification?.id,
    }) : action);
    return messages.flatMap(message => {
        if (message.sender !== 'bot' || !message.sections?.length) {
            const staleSummary = message !== latestBot && (message.trip_summary || message.trip_details_form);
            return message.suggested_actions?.some(action => action.question_id) || staleSummary
                ? [{ ...message, suggested_actions: actions(message), ...(staleSummary ? { trip_summary: null, trip_details_form: null } : {}) }]
                : [message];
        }
        return message.sections.map((section, index) => {
            const last = index === message.sections.length - 1;
            const destination = section.constraints?.destination_name;
            let text = '### ' + section.heading + (destination ? ' — ' + destination : '') + '\n\n' + section.answer;
            if (last) {
                // Prices sit with the results they qualify; planning notices and
                // the one open question come last, each rendered once.
                if (message.price_effective_note) text += '\n\n' + message.price_effective_note;
                if (message.section_footer) text += '\n\n' + message.section_footer;
                if (message.dialogue_notice) text += '\n\n' + message.dialogue_notice;
                if (message.clarification?.text) text += '\n\n' + message.clarification.text;
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
                // The question metadata must survive section splitting, or the
                // last section cannot render its control.
                clarification: last ? (message.clarification ?? null) : null,
                trip_summary: last && message === latestBot ? (message.trip_summary ?? null) : null,
                trip_details_form: last && message === latestBot ? (message.trip_details_form ?? null) : null,
                suggested_actions: last ? actions(message) : [],
            };
        });
    });
}
