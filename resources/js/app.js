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

const initializeBudgetTargetSelectors = (form) => {
	const propertySelect = form.querySelector('[data-property-select]');
	const lotSelect = form.querySelector('[data-lot-select][data-budget-target]');
	const facilitySelect = form.querySelector('[data-facility-select][data-budget-target]');

	if (!propertySelect || !lotSelect || !facilitySelect) {
		return;
	}

	const propertySelected = propertySelect.value !== '';
	const bothTargetsSelected = lotSelect.value !== '' && facilitySelect.value !== '';

	lotSelect.disabled = !propertySelected || (!bothTargetsSelected && facilitySelect.value !== '');
	facilitySelect.disabled = !propertySelected || (!bothTargetsSelected && lotSelect.value !== '');
};

const initializeLotFilters = () => {
	document.querySelectorAll('form').forEach((form) => filterPropertyOptions(form));
	document.querySelectorAll('form').forEach((form) => initializeBudgetTargetSelectors(form));
};

const initializeSitePlanViewers = () => {
	document.querySelectorAll('[data-site-plan-viewer]:not([data-site-plan-initialized])').forEach((viewer) => {
		viewer.dataset.sitePlanInitialized = 'true';

		const lotButtons = Array.from(viewer.querySelectorAll('[data-site-plan-lot]'));
		const emptyState = viewer.querySelector('[data-site-plan-empty]');
		const detail = viewer.querySelector('[data-site-plan-detail]');
		const label = viewer.querySelector('[data-site-plan-lot-label]');
		const status = viewer.querySelector('[data-site-plan-lot-status]');
		const price = viewer.querySelector('[data-site-plan-lot-price]');
		const notes = viewer.querySelector('[data-site-plan-lot-notes]');
		const notesSection = viewer.querySelector('[data-site-plan-notes-section]');

		const selectLot = (lotButton) => {
			lotButtons.forEach((button) => {
				const isSelected = button === lotButton;
				button.setAttribute('aria-pressed', String(isSelected));
				button.style.outline = isSelected ? '3px solid white' : '';
				button.style.boxShadow = isSelected ? '0 0 0 5px #10233d' : '';
				button.style.zIndex = isSelected ? '30' : '';
			});

			if (emptyState) {
				emptyState.hidden = true;
			}

			if (detail) {
				detail.hidden = false;
			}

			if (label) {
				label.textContent = lotButton.dataset.lotLabel ?? '';
			}

			if (status) {
				status.textContent = lotButton.dataset.lotStatus ?? '';
				status.style.backgroundColor = `${lotButton.dataset.lotColor}26`;
				status.style.color = lotButton.dataset.lotColor ?? '';
			}

			if (price) {
				const amount = Number(lotButton.dataset.lotPrice ?? 0);
				price.textContent = new Intl.NumberFormat('id-ID', {
					style: 'currency',
					currency: 'IDR',
					maximumFractionDigits: 0,
				}).format(amount);
			}

			if (notes) {
				notes.textContent = lotButton.dataset.lotNotes ?? '';
				notesSection.hidden = ! notes.textContent.trim();
			}
		};

		lotButtons.forEach((button) => {
			button.addEventListener('click', () => {
				const url = new URL(viewer.dataset.dashboardUrl, window.location.origin);
				const selectedFilters = new URLSearchParams(window.location.search);
				const sameLotIsFiltered = selectedFilters.get('property_id') === viewer.dataset.propertyId
					&& selectedFilters.get('lot_id') === button.dataset.lotId;

				if (sameLotIsFiltered) {
					selectLot(button);

					return;
				}

				url.searchParams.set('property_id', viewer.dataset.propertyId);
				url.searchParams.set('lot_id', button.dataset.lotId);
				window.location.assign(url);
			});
		});

		const selectedLot = lotButtons.find((button) => button.getAttribute('aria-pressed') === 'true');

		if (selectedLot) {
			selectLot(selectedLot);
		}
	});
};

const initializeSitePlanEditors = () => {
	document.querySelectorAll('[data-site-plan-editor]:not([data-site-plan-initialized])').forEach((form) => {
		form.dataset.sitePlanInitialized = 'true';

		const lotSelect = form.querySelector('[data-site-plan-lot-select]');
		const canvas = form.querySelector('[data-site-plan-canvas]');
		const draft = form.querySelector('[data-site-plan-draft]');
		const drawStatus = form.querySelector('[data-site-plan-draw-status]');
		const confirmDialog = form.querySelector('[data-site-plan-confirm]');
		const confirmLotLabel = form.querySelector('[data-site-plan-confirm-lot]');
		const confirmCancel = form.querySelector('[data-site-plan-confirm-cancel]');
		const confirmAccept = form.querySelector('[data-site-plan-confirm-accept]');
		const coordinateInputs = Object.fromEntries(
			Array.from(form.querySelectorAll('[data-site-plan-coordinate]'))
				.map((input) => [input.dataset.sitePlanCoordinate, input]),
		);
		let startPoint = null;
		let activePointerId = null;
		let hasArea = false;
		let overwriteConfirmed = false;

		if (! lotSelect || ! canvas || ! draft) {
			return;
		}

		const currentOption = () => lotSelect.selectedOptions[0];
		const updateFormAction = () => {
			const option = currentOption();

			if (option?.dataset.url) {
				form.action = option.dataset.url;
			}
		};
		const setCoordinates = (area) => {
			Object.entries(area).forEach(([key, value]) => {
				if (coordinateInputs[key]) {
					coordinateInputs[key].value = Number(value).toFixed(2);
				}
			});
		};
		const showArea = (area) => {
			draft.hidden = false;
			draft.style.left = `${area.x}%`;
			draft.style.top = `${area.y}%`;
			draft.style.width = `${area.width}%`;
			draft.style.height = `${area.height}%`;
			hasArea = true;
		};
		const loadSelectedArea = () => {
			const option = currentOption();
			const area = {
				x: option?.dataset.x,
				y: option?.dataset.y,
				width: option?.dataset.width,
				height: option?.dataset.height,
			};

			updateFormAction();

			if (Object.values(area).every((value) => value !== undefined && value !== '')) {
				setCoordinates(area);
				showArea(area);
				drawStatus.textContent = 'Area kavling ini sudah ditandai. Tarik ulang untuk mengubah posisinya.';
			} else {
				Object.values(coordinateInputs).forEach((input) => {
					input.value = '';
				});
				draft.hidden = true;
				hasArea = false;
				drawStatus.textContent = 'Tarik pada denah untuk memilih area kavling.';
			}
		};
		const pointFromEvent = (event) => {
			const bounds = canvas.getBoundingClientRect();

			return {
				x: Math.min(100, Math.max(0, ((event.clientX - bounds.left) / bounds.width) * 100)),
				y: Math.min(100, Math.max(0, ((event.clientY - bounds.top) / bounds.height) * 100)),
			};
		};
		const areaBetween = (first, second) => ({
			x: Math.min(first.x, second.x),
			y: Math.min(first.y, second.y),
			width: Math.abs(second.x - first.x),
			height: Math.abs(second.y - first.y),
		});

		lotSelect.addEventListener('change', loadSelectedArea);
		loadSelectedArea();

		canvas.addEventListener('pointerdown', (event) => {
			if (event.button !== 0) {
				return;
			}

			event.preventDefault();
			startPoint = pointFromEvent(event);
			activePointerId = event.pointerId;
			canvas.setPointerCapture(event.pointerId);
			showArea({ ...startPoint, width: 0, height: 0 });
		});

		canvas.addEventListener('pointermove', (event) => {
			if (event.pointerId !== activePointerId || ! startPoint) {
				return;
			}

			showArea(areaBetween(startPoint, pointFromEvent(event)));
		});

		canvas.addEventListener('pointerup', (event) => {
			if (event.pointerId !== activePointerId || ! startPoint) {
				return;
			}

			const area = areaBetween(startPoint, pointFromEvent(event));
			startPoint = null;
			activePointerId = null;

			if (area.width < 0.15 || area.height < 0.15) {
				loadSelectedArea();
				drawStatus.textContent = 'Tarikan terlalu kecil. Tarik kembali untuk memilih area.';

				return;
			}

			setCoordinates(area);
			showArea(area);
			drawStatus.textContent = 'Area siap disimpan.';
		});

		canvas.addEventListener('pointercancel', () => {
			startPoint = null;
			activePointerId = null;
			loadSelectedArea();
		});

		confirmCancel?.addEventListener('click', () => confirmDialog.close());
		confirmDialog?.addEventListener('click', (event) => {
			if (event.target === confirmDialog) {
				confirmDialog.close();
			}
		});
		confirmAccept?.addEventListener('click', () => {
			overwriteConfirmed = true;
			confirmDialog.close();
			form.requestSubmit();
		});

		form.addEventListener('submit', (event) => {
			if (! hasArea) {
				event.preventDefault();
				drawStatus.textContent = 'Tandai area kavling pada gambar sebelum menyimpan.';

				return;
			}

			if (overwriteConfirmed) {
				overwriteConfirmed = false;

				return;
			}

			const option = currentOption();
			const alreadyMarked = ['x', 'y', 'width', 'height']
				.every((key) => option?.dataset[key] !== undefined && option.dataset[key] !== '');

			if (alreadyMarked && confirmDialog) {
				event.preventDefault();
				confirmLotLabel.textContent = option.textContent.trim();
				confirmDialog.showModal();
			}
		});
	});
};

initializeCurrencyInputs();
initializeLotFilters();
initializeSitePlanViewers();
initializeSitePlanEditors();
document.addEventListener('livewire:navigated', () => {
	initializeCurrencyInputs();
	initializeLotFilters();
	initializeSitePlanViewers();
	initializeSitePlanEditors();
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
			const expenseBudgetSelect = form.querySelector('[data-expense-budget-select]');

			const selectedBudgetPropertyId = expenseBudgetSelect?.selectedOptions[0]?.dataset.propertyId;

			if (expenseBudgetSelect && selectedBudgetPropertyId && selectedBudgetPropertyId !== event.target.value) {
				expenseBudgetSelect.value = '';
			}

			filterPropertyOptions(form);
			initializeBudgetTargetSelectors(form);
		}
	}
}, true);
document.addEventListener('change', (event) => {
	if (!(event.target instanceof HTMLSelectElement) || !event.target.matches('[data-budget-target]')) {
		return;
	}

	const form = event.target.closest('form');

	if (event.target.value && form) {
		form.querySelectorAll('[data-budget-target]').forEach((select) => {
			if (select !== event.target) {
				select.value = '';
			}
		});
	}

	if (form) {
		initializeBudgetTargetSelectors(form);
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
