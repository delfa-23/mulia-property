import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

const normalizeCurrencyValue = (value) => {
	const [integerPart, ...decimalParts] = String(value).replace(/[^\d,]/g, '').split(',');
	const integer = integerPart.replace(/\D/g, '');
	const decimal = decimalParts.join('').replace(/\D/g, '');

	return decimal ? `${integer}.${decimal}` : integer;
};

const formatCurrencyValue = (value, canonical = false) => {
	const source = String(value).trim();
	const canonicalSource = source.replace(/^Rp\s*/i, '').replace(/\s/g, '');
	const groupedCanonicalSource = canonical
		&& /^\d{1,3}(?:\.\d{3})+(?:,\d+)?$/.test(canonicalSource)
		? canonicalSource.replace(/\./g, '').replace(',', '.')
		: canonicalSource;
	const parts = canonical
		? groupedCanonicalSource.split('.')
		: source.replace(/[^\d,]/g, '').split(',');
	const integer = parts[0].replace(/\D/g, '');
	const decimal = (parts.slice(1).join('') || '').replace(/\D/g, '').replace(/0+$/, '');
	const hasDecimalSeparator = decimal.length > 0 || (!canonical && source.includes(','));

	if (! integer) {
		return '';
	}

	const groupedInteger = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

	return `Rp ${groupedInteger}${hasDecimalSeparator ? `,${decimal}` : ''}`;
};

const updateCurrencyInput = (input, canonical = false) => {
	const previousValue = input.value;
	const caretPosition = input.selectionStart ?? previousValue.length;
	const beforeCaret = previousValue.slice(0, caretPosition);
	const integerDigitsBeforeCaret = (beforeCaret.split(',')[0].match(/\d/g) ?? []).length;
	const decimalDigitsBeforeCaret = beforeCaret.includes(',')
		? (beforeCaret.split(',').slice(1).join(',').match(/\d/g) ?? []).length
		: null;
	const formattedValue = formatCurrencyValue(previousValue, canonical);

	input.value = formattedValue;

	const decimalSeparator = formattedValue.indexOf(',');
	let nextCaretPosition = formattedValue.length;

	if (decimalDigitsBeforeCaret !== null && decimalSeparator !== -1) {
		nextCaretPosition = decimalSeparator + 1 + decimalDigitsBeforeCaret;
	} else if (caretPosition < previousValue.length) {
		let digitsSeen = 0;
		nextCaretPosition = formattedValue.startsWith('Rp ') ? 3 : 0;

		while (nextCaretPosition < formattedValue.length && digitsSeen < integerDigitsBeforeCaret) {
			if (/\d/.test(formattedValue[nextCaretPosition])) {
				digitsSeen++;
			}

			nextCaretPosition++;
		}
	}

	input.setSelectionRange(nextCaretPosition, nextCaretPosition);

	const normalizedValue = normalizeCurrencyValue(formattedValue);
	const numericValue = normalizedValue === '' ? null : Number(normalizedValue);
	const minimum = input.dataset.currencyMin === undefined ? null : Number(input.dataset.currencyMin);
	const maximum = input.dataset.currencyMax === undefined ? null : Number(input.dataset.currencyMax);
	const step = input.dataset.currencyStep === undefined ? null : Number(input.dataset.currencyStep);
	const offStep = numericValue !== null && step !== null
		&& Math.abs(numericValue / step - Math.round(numericValue / step)) > 0.000000001;

	input.setCustomValidity(
		numericValue !== null && minimum !== null && numericValue < minimum
			? `Nominal minimal Rp ${minimum.toLocaleString('id-ID')}.`
			: numericValue !== null && maximum !== null && numericValue > maximum
				? `Nominal maksimal Rp ${maximum.toLocaleString('id-ID')}.`
				: offStep
					? `Nominal harus merupakan kelipatan Rp ${step.toLocaleString('id-ID')}.`
				: '',
	);
};

const initializeCurrencyInputs = () => {
	document.querySelectorAll('[data-currency-input]').forEach((input) => updateCurrencyInput(input, true));
};

const filterPropertyOptions = (form) => {
	const propertySelect = form.querySelector('[data-property-select]');

	if (!propertySelect) {
		return;
	}

	const propertyId = propertySelect.value;
	const dependentSelects = form.querySelectorAll('[data-lot-select], [data-facility-select]');

	dependentSelects.forEach((select) => {
		const selectedValue = select.value;
		let selectedOptionMatchesProperty = selectedValue === '';

		Array.from(select.options).forEach((option) => {
			if (!option.hasAttribute('data-property-id')) {
				return;
			}

			const matchesProperty = propertyId !== '' && option.dataset.propertyId === propertyId;
			option.hidden = !matchesProperty;
			option.disabled = !matchesProperty;

			if (option.value === selectedValue && matchesProperty) {
				selectedOptionMatchesProperty = true;
			}
		});

		select.disabled = propertyId === '';

		if (!selectedOptionMatchesProperty) {
			select.value = '';
		}
	});
};

const initializeExpenseTargetSelectors = (form) => {
	const propertySelect = form.querySelector('[data-property-select]');
	const lotSelect = form.querySelector('[data-lot-select][data-expense-target]');
	const facilitySelect = form.querySelector('[data-facility-select][data-expense-target]');

	if (!propertySelect || !lotSelect || !facilitySelect) {
		return;
	}

	const propertySelected = propertySelect.value !== '';

	if (lotSelect.value !== '' && facilitySelect.value !== '') {
		lotSelect.disabled = !propertySelected;
		facilitySelect.disabled = !propertySelected;

		return;
	}

	lotSelect.disabled = !propertySelected || facilitySelect.value !== '';
	facilitySelect.disabled = !propertySelected || lotSelect.value !== '';
};

const initializeLotFilters = () => {
	document.querySelectorAll('form').forEach((form) => filterPropertyOptions(form));
	document.querySelectorAll('form').forEach((form) => initializeExpenseTargetSelectors(form));
};

initializeCurrencyInputs();
initializeLotFilters();
document.addEventListener('livewire:navigated', () => {
	initializeCurrencyInputs();
	initializeLotFilters();
});
document.addEventListener('input', (event) => {
	if (event.target instanceof HTMLInputElement && event.target.matches('[data-currency-input]')) {
		updateCurrencyInput(event.target);
	}
});
document.addEventListener('change', (event) => {
	if (event.target instanceof HTMLSelectElement && event.target.matches('[data-property-select]')) {
		const form = event.target.closest('form');

		if (form) {
			filterPropertyOptions(form);
			initializeExpenseTargetSelectors(form);
		}
	}
}, true);
document.addEventListener('change', (event) => {
	if (!(event.target instanceof HTMLSelectElement) || !event.target.matches('[data-expense-target]')) {
		return;
	}

	const form = event.target.closest('form');
	const otherTarget = form?.querySelector(
		event.target.matches('[data-lot-select]')
			? '[data-facility-select][data-expense-target]'
			: '[data-lot-select][data-expense-target]',
	);

	if (event.target.value && otherTarget) {
		otherTarget.value = '';
	}

	if (form) {
		initializeExpenseTargetSelectors(form);
	}
});
document.addEventListener('change', (event) => {
	if (event.target instanceof HTMLSelectElement && event.target.matches('[data-budget-target]') && event.target.value) {
		const form = event.target.closest('form');

		form?.querySelectorAll('[data-budget-target]').forEach((select) => {
			if (select !== event.target) {
				select.value = '';
			}
		});
	}
});
document.addEventListener('submit', (event) => {
	if (!(event.target instanceof HTMLFormElement)) {
		return;
	}

	event.target.querySelectorAll('[data-currency-input]').forEach((input) => {
		input.value = normalizeCurrencyValue(input.value);
	});
}, true);

window.Alpine = Alpine;

await import('../../vendor/livewire/flux/dist/flux-lite.min.js');

Alpine.data('fluxInputViewable', () => ({
	open: false,

	toggle() {
		this.open = ! this.open;

		const input = this.$el.closest('[data-flux-input]')?.querySelector('input');

		if (input) {
			input.setAttribute('type', this.open ? 'text' : 'password');
		}
	},

	init() {
		const input = this.$el.closest('[data-flux-input]')?.querySelector('input');

		if (! input) {
			return;
		}

		new MutationObserver(() => {
			const type = this.open ? 'text' : 'password';

			if (input.getAttribute('type') !== type) {
				input.setAttribute('type', type);
			}
		}).observe(input, { attributes: true, attributeFilter: ['type'] });
	},
}));

Alpine.data('fluxModal', (name, scope) => ({
	handleShow(event) {
		if (event.detail.name !== name) {
			return;
		}

		if (scope && event.detail.scope === scope) {
			this.$el.showModal();
		} else if (! event.detail.scope) {
			this.$el.showModal();
		}
	},

	handleClose(event) {
		if (! event.detail.name) {
			this.$el.close();
		} else if (event.detail.name === name && (! scope || event.detail.scope === scope || ! event.detail.scope)) {
			this.$el.close();
		}
	},
}));

const sidebar = document.querySelector('[data-flux-sidebar]');
const mobile = window.matchMedia('(max-width: 1023px)');

if (sidebar) {
	const updateViewportState = () => {
		sidebar.toggleAttribute('data-flux-sidebar-on-mobile', mobile.matches);
		sidebar.toggleAttribute('data-flux-sidebar-collapsed-mobile', mobile.matches);
		sidebar.removeAttribute('data-flux-sidebar-cloak');
	};

	const setCollapsed = (collapsed) => {
		sidebar.toggleAttribute('data-flux-sidebar-collapsed-mobile', collapsed);
		sidebar.querySelectorAll('[data-flux-sidebar-collapse] button').forEach((button) => {
			button.setAttribute('aria-expanded', String(! collapsed));
		});
		document.querySelectorAll('header [data-flux-sidebar-collapse] button').forEach((button) => {
			button.setAttribute('aria-expanded', String(! collapsed));
		});
	};

	updateViewportState();
	mobile.addEventListener('change', updateViewportState);

	document.addEventListener('click', (event) => {
		if (! mobile.matches || ! (event.target instanceof Element)) {
			return;
		}

		const trigger = event.target.closest('[data-flux-sidebar-collapse]');

		if (! trigger) {
			return;
		}

		event.preventDefault();
		setCollapsed(! sidebar.hasAttribute('data-flux-sidebar-collapsed-mobile'));
	});

	document.addEventListener('keydown', (event) => {
		if (mobile.matches && event.key === 'Escape') {
			setCollapsed(true);
		}
	});

	sidebar.addEventListener('click', (event) => {
		if (mobile.matches && event.target instanceof Element && event.target.closest('a[wire\\:navigate]')) {
			setCollapsed(true);
		}
	});
}

Livewire.start();
