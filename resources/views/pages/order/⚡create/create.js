// Add your JavaScript here

document.getElementById('sender-select').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const dataAttr = selectedOption.getAttribute('data-attr');
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
        country:'VIETNAM',
    };

    // Set sender data
    $wire.set('sender', dataSender).then(() => {
        // Sau khi Livewire cập nhật, cập nhật TomSelect cho city và ward
        setTimeout(() => {
            // Cập nhật city select
            const citySelect = document.querySelector('select[name="sender.id_city"]');
            if (citySelect && citySelect.tomselect && dataSender.id_city) {
                citySelect.tomselect.setValue(dataSender.id_city);
            }

            // Đợi một chút để ward options được load
            setTimeout(() => {
                const wardSelect = document.querySelector('select[name="sender.id_ward"]');
                if (wardSelect && wardSelect.tomselect && dataSender.id_ward) {
                    wardSelect.tomselect.setValue(dataSender.id_ward);
                }
            }, 300);
        }, 100);
    });
});
