function syncSenderLocation(dataSender) {
    setTimeout(() => {
        const citySelect = document.querySelector('select[name="sender.id_city"]');
        if (citySelect && citySelect.tomselect && dataSender.id_city) {
            citySelect.tomselect.setValue(dataSender.id_city);
        }

        setTimeout(() => {
            const wardSelect = document.querySelector('select[name="sender.id_ward"]');
            if (wardSelect && wardSelect.tomselect && dataSender.id_ward) {
                wardSelect.tomselect.setValue(dataSender.id_ward);
            }
        }, 300);
    }, 100);
}

document.addEventListener('change', (event) => {
    const senderSelect = event.target.closest('#sender-select');
    if (senderSelect) {
        const selectedOption = senderSelect.options[senderSelect.selectedIndex];
        const dataAttr = selectedOption?.getAttribute('data-attr');
        const parsedData = dataAttr ? JSON.parse(dataAttr) : null;
        const dataSender = {
            id: parsedData?.id || null,
            company: parsedData?.company_name || '',
            company_short_name: parsedData?.company_short_name || '',
            fullname: parsedData?.fullname || '',
            phone: parsedData?.phone || '',
            email: parsedData?.email || '',
            address: parsedData?.address || '',
            id_city: parsedData?.city_id || null,
            id_ward: parsedData?.ward_id || null,
            type: parsedData?.type || null,
            country: 'VIETNAM',
        };
        const nextIdCtv = $wire.fixedIdCtv || dataSender.id || null;
        $wire.set('idCtv', nextIdCtv);
        $wire.set('sender', dataSender).then(() => {
            syncSenderLocation(dataSender);
        });
        return;
    }

    const receiverSelect = event.target.closest('#receiver-select');
    if (receiverSelect) {
        const selectedOption = receiverSelect.options[receiverSelect.selectedIndex];
        const dataAttr = selectedOption?.getAttribute('data-attr');
        const parsedData = dataAttr ? JSON.parse(dataAttr) : null;
        const dataReceiver = {
            id: parsedData?.id || null,
            company: parsedData?.company || '',
            fullname: parsedData?.fullname || '',
            phone: parsedData?.phone || '',
            email: parsedData?.email || '',
            mavung: parsedData?.mavung || '',
            country_id: parsedData?.country_id || null,
            address: parsedData?.address || '',
            state: parsedData?.state || '',
            city: parsedData?.city || '',
            postcode: parsedData?.postcode || '',
            vsvx: false,
            address: parsedData?.address || '',
        };
        $wire.set('receiver', dataReceiver);
        $wire.$dispatch('receiverUpdated');
    }
});
