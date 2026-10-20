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

const initializeLotImageInputs = () => {
	document.querySelectorAll('[data-lot-image-inputs]:not([data-lot-image-inputs-initialized])').forEach((fields) => {
		fields.dataset.lotImageInputsInitialized = 'true';
		const form = fields.closest('form');

		form?.querySelector('[data-add-lot-image-field]')?.addEventListener('click', () => {
			const field = document.createElement('div');
			field.dataset.lotImageField = '';
			field.className = 'flex gap-2';

			const input = document.createElement('input');
			input.type = 'file';
			input.name = 'images[]';
			input.accept = 'image/jpeg,image/png,image/webp';
			input.className = 'block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm';

			const removeButton = document.createElement('button');
			removeButton.type = 'button';
			removeButton.dataset.removeLotImageField = '';
			removeButton.className = 'rounded-lg border border-gray-300 px-3 text-lg';
			removeButton.setAttribute('aria-label', 'Hapus pilihan foto');
			removeButton.textContent = '-';

			field.append(input, removeButton);
			fields.append(field);
		});

		fields.addEventListener('click', (event) => {
			const removeButton = event.target.closest('[data-remove-lot-image-field]');
			if (removeButton) {
				removeButton.closest('[data-lot-image-field]')?.remove();
			}
		});
	});

	document.querySelectorAll('[data-lot-saved-image]:not([data-lot-saved-image-initialized])').forEach((photo) => {
		photo.dataset.lotSavedImageInitialized = 'true';
		const removeInput = photo.querySelector('[data-remove-lot-saved-image]');
		const toggleButton = photo.querySelector('[data-toggle-lot-saved-image]');

		toggleButton?.addEventListener('click', () => {
			if (!removeInput || !toggleButton) {
				return;
			}

			removeInput.checked = !removeInput.checked;
			toggleButton.textContent = removeInput.checked ? '+' : '-';
			photo.classList.toggle('opacity-40', removeInput.checked);
			toggleButton.setAttribute('aria-label', removeInput.checked ? 'Batalkan hapus foto' : 'Hapus foto');
		});
	});
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

const initializeWorkflowBookingModals = () => {
	document.querySelectorAll('[data-workflow-bookings-modal]:not([data-workflow-bookings-modal-initialized])').forEach((modal) => {
		modal.dataset.workflowBookingsModalInitialized = 'true';
		modal.querySelector('[data-workflow-bookings-modal-close]')?.addEventListener('click', () => modal.close());
		modal.addEventListener('click', (event) => {
			if (event.target === modal) {
				modal.close();
			}
		});

		if (modal.hasAttribute('data-workflow-bookings-modal-open')) {
			modal.showModal();
		}
	});
};

const initializeSitePlanViewers = () => {
	document.querySelectorAll('[data-site-plan-property-select]:not([data-site-plan-initialized])').forEach((select) => {
		select.dataset.sitePlanInitialized = 'true';
		select.addEventListener('change', () => select.form?.requestSubmit());
	});

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
		const lotImages = viewer.querySelector('[data-site-plan-lot-images]');
		const lotImageEmpty = viewer.querySelector('[data-site-plan-lot-image-empty]');
		const lotAction = viewer.querySelector('[data-site-plan-lot-action]');
		const lotProperty = viewer.querySelector('[data-site-plan-lot-property]');
		const lotBlock = viewer.querySelector('[data-site-plan-lot-block]');
		const lotCustomer = viewer.querySelector('[data-site-plan-lot-customer]');
		const lotCustomerRow = viewer.querySelector('[data-site-plan-customer-row]');
		const lotSales = viewer.querySelector('[data-site-plan-lot-sales]');
		const lotSalesRow = viewer.querySelector('[data-site-plan-sales-row]');
		const unmappedMessage = viewer.querySelector('[data-site-plan-unmapped]');
		const priceSection = viewer.querySelector('[data-site-plan-price-section]');
		const viewport = viewer.querySelector('[data-site-plan-viewport]');
		const zoomContent = viewer.querySelector('[data-site-plan-zoom-content]');
		const zoomLabel = viewer.querySelector('[data-site-plan-zoom-label]');
		const modal = viewer.querySelector('[data-site-plan-modal]');
		const modalClose = viewer.querySelector('[data-site-plan-modal-close]');

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

			if (lotProperty) {
				lotProperty.textContent = lotButton.dataset.lotProperty ?? '';
			}

			if (lotBlock) {
				lotBlock.textContent = lotButton.dataset.lotMapped === 'false'
					? 'Belum dihubungkan'
					: `${lotButton.dataset.lotBlock ?? ''}/${lotButton.dataset.lotNumber ?? ''}`;
			}

			if (lotCustomer) {
				lotCustomer.textContent = lotButton.dataset.lotCustomer ?? '';
				lotCustomerRow.hidden = ! lotCustomer.textContent;
			}

			if (lotSales) {
				lotSales.textContent = lotButton.dataset.lotSales ?? '';
				lotSalesRow.hidden = ! lotSales.textContent;
			}

			if (status) {
				status.textContent = lotButton.dataset.lotStatus ?? '';
				status.style.backgroundColor = `${lotButton.dataset.lotColor}26`;
				status.style.color = lotButton.dataset.lotColor ?? '';
			}

			if (price) {
				const hasLot = lotButton.dataset.lotMapped !== 'false';
				priceSection.hidden = ! hasLot;
				if (hasLot) {
					const amount = Number(lotButton.dataset.lotPrice ?? 0);
					price.textContent = new Intl.NumberFormat('id-ID', {
						style: 'currency',
						currency: 'IDR',
						maximumFractionDigits: 0,
					}).format(amount);
				}
			}

			if (unmappedMessage) {
				unmappedMessage.hidden = lotButton.dataset.lotMapped !== 'false';
			}

			if (notes) {
				notes.textContent = lotButton.dataset.lotNotes ?? '';
				notesSection.hidden = ! notes.textContent.trim();
			}

			if (lotImages) {
				const imageUrls = JSON.parse(lotButton.dataset.lotImages ?? '[]');
				lotImages.replaceChildren(...imageUrls.map((imageUrl) => {
					const image = document.createElement('img');
					image.src = imageUrl;
					image.alt = `Foto kavling ${lotButton.dataset.lotLabel ?? ''}`;
					image.className = 'aspect-4/3 w-full rounded-lg bg-slate-100 object-cover dark:bg-zinc-800';
					return image;
				}));
				lotImages.hidden = imageUrls.length === 0;
				lotImageEmpty.hidden = imageUrls.length > 0;
			}

			if (lotAction) {
				const actionUrl = lotButton.dataset.lotActionUrl ?? '';
				lotAction.hidden = ! actionUrl || lotButton.dataset.lotMapped === 'false';
				lotAction.style.display = actionUrl ? 'inline-flex' : 'none';
				lotAction.href = actionUrl;
				lotAction.textContent = lotButton.dataset.lotActionLabel ?? '';
			}
		};

		lotButtons.forEach((button) => {
			button.addEventListener('keydown', (event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					button.click();
				}
			});

			button.addEventListener('click', () => {
				if (modal) {
					selectLot(button);
					modal.showModal();

					return;
				}

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

		if (viewport && zoomContent && zoomLabel) {
			let zoom = 1;
			let panStart = null;
			const applyZoom = () => {
				zoomContent.style.width = `${zoom * 100}%`;
				zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
			};

			viewer.querySelector('[data-site-plan-zoom-in]')?.addEventListener('click', () => {
				zoom = Math.min(4, Number((zoom + 0.25).toFixed(2)));
				applyZoom();
			});
			viewer.querySelector('[data-site-plan-zoom-out]')?.addEventListener('click', () => {
				zoom = Math.max(1, Number((zoom - 0.25).toFixed(2)));
				applyZoom();
			});
			viewer.querySelector('[data-site-plan-zoom-reset]')?.addEventListener('click', () => {
				zoom = 1;
				viewport.scrollTo({ left: 0, top: 0, behavior: 'smooth' });
				applyZoom();
			});

			viewport.addEventListener('pointerdown', (event) => {
				if (event.button !== 0 || event.target.closest('[data-site-plan-lot]')) {
					return;
				}

				panStart = { x: event.clientX, y: event.clientY, left: viewport.scrollLeft, top: viewport.scrollTop };
				viewport.setPointerCapture(event.pointerId);
				viewport.classList.add('cursor-grabbing');
			});
			viewport.addEventListener('pointermove', (event) => {
				if (! panStart) {
					return;
				}

				viewport.scrollLeft = panStart.left - (event.clientX - panStart.x);
				viewport.scrollTop = panStart.top - (event.clientY - panStart.y);
			});
			const stopPan = () => {
				panStart = null;
				viewport.classList.remove('cursor-grabbing');
			};
			viewport.addEventListener('pointerup', stopPan);
			viewport.addEventListener('pointercancel', stopPan);
		}

		modalClose?.addEventListener('click', () => modal.close());
		modal?.addEventListener('click', (event) => {
			if (event.target === modal) {
				modal.close();
			}
		});
		modal?.addEventListener('close', () => {
			lotButtons.forEach((button) => {
				button.setAttribute('aria-pressed', 'false');
				button.style.outline = '';
				button.style.boxShadow = '';
				button.style.zIndex = '';
			});

			if (emptyState) {
				emptyState.hidden = false;
			}

			if (detail) {
				detail.hidden = true;
			}
		});

		const selectedLot = lotButtons.find((button) => button.getAttribute('aria-pressed') === 'true');

		if (selectedLot) {
			selectLot(selectedLot);
			if (modal && new URLSearchParams(window.location.search).has('lot_id')) {
				modal.showModal();
			}
		}
	});
};

let alertNotificationCountPollInterval = null;

const initializeAlertNotificationCount = () => {
	const badge = document.querySelector('[data-alert-count]');

	if (!badge) {
		return;
	}

	if (alertNotificationCountPollInterval !== null) {
		window.clearInterval(alertNotificationCountPollInterval);
	}

	const poll = async () => {
		if (document.hidden) {
			return;
		}

		try {
			const response = await fetch(badge.dataset.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
				},
			});

			if (!response.ok) {
				console.error('Failed to update alert count:', response.status);
				return;
			}

			const { count } = await response.json();
			const unreadCount = Number(count);

			if (!Number.isFinite(unreadCount) || unreadCount < 0) {
				console.error('Failed to update alert count: invalid count response.');
				return;
			}

			badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
			badge.setAttribute('aria-label', `${unreadCount} alert belum dibaca`);
			badge.classList.toggle('hidden', unreadCount === 0);
		} catch (error) {
			console.error('Failed to update alert count:', error);
		}
	};

	poll();
	alertNotificationCountPollInterval = window.setInterval(poll, 30000);
};

const initializeSitePlanPolygonEditors = () => {
	document.querySelectorAll('[data-site-plan-polygon-form]:not([data-site-plan-initialized])').forEach((form) => {
		form.dataset.sitePlanInitialized = 'true';

		const svg = form.closest('#site-plan-editor')?.querySelector('[data-site-plan-polygon-canvas]');
		const viewport = form.closest('#site-plan-editor')?.querySelector('[data-polygon-viewport]');
		const zoomContent = form.closest('#site-plan-editor')?.querySelector('[data-site-plan-zoom-content]');
		const zoomLabel = form.closest('#site-plan-editor')?.querySelector('[data-site-plan-zoom-label]');
		const draft = svg?.querySelector('[data-site-plan-draft-polygon]');
		const handles = svg?.querySelector('[data-site-plan-point-handles]');
		const fields = form.querySelector('[data-site-plan-polygon-fields]');
		const lotSelect = form.querySelector('[data-site-plan-area-lot]');
		const labelInput = form.querySelector('[data-site-plan-area-label-input]');
		const labelField = form.querySelector('[data-site-plan-area-label]');
		const methodField = form.querySelector('[data-site-plan-area-method]');
		const deleteForm = form.closest('#site-plan-editor')?.querySelector('[data-site-plan-area-delete-form]');
		const status = form.querySelector('[data-site-plan-area-status]');
		let points = [];
		let selectedArea = null;
		let drawingNewArea = true;
		let activeLegacyShape = null;
		let selectedPointIndex = null;
		let mode = 'draw';
		let draggedPointIndex = null;
		let panStart = null;
		let zoom = 1;

		if (! svg || ! draft || ! handles || ! fields || ! lotSelect || ! labelInput || ! labelField || ! methodField || ! status) {
			return;
		}

		const pointFromEvent = (event) => {
			const bounds = svg.getBoundingClientRect();

			return {
				x: Math.min(100, Math.max(0, ((event.clientX - bounds.left) / bounds.width) * 100)),
				y: Math.min(100, Math.max(0, ((event.clientY - bounds.top) / bounds.height) * 100)),
			};
		};
		const setStatus = (message) => {
			status.textContent = message;
		};
		const renderPoints = () => {
			const pointsString = points.map((point) => `${point.x},${point.y}`).join(' ');
			draft.setAttribute('points', pointsString);
			draft.hidden = points.length === 0;
			fields.replaceChildren();

			points.forEach((point, index) => {
				[['x', point.x], ['y', point.y]].forEach(([axis, value]) => {
					const input = document.createElement('input');
					input.type = 'hidden';
					input.name = `polygon[${index}][${axis}]`;
					input.value = Number(value).toFixed(4);
					fields.append(input);
				});
			});

			renderHandles();
		};
		const renderHandles = () => {
			handles.replaceChildren();

			if (selectedArea === null && ! drawingNewArea) {
				return;
			}

			points.forEach((point, index) => {
				const handle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
				handle.setAttribute('cx', String(point.x));
				handle.setAttribute('cy', String(point.y));
				handle.setAttribute('r', '0.65');
				handle.setAttribute('fill', '#ffffff');
				handle.setAttribute('stroke', '#1d4ed8');
				handle.setAttribute('stroke-width', '0.3');
				handle.setAttribute('vector-effect', 'non-scaling-stroke');
				handle.setAttribute('data-site-plan-point-handle', String(index));
				handle.setAttribute('tabindex', '0');
				handle.setAttribute('role', 'button');
				handle.setAttribute('aria-label', `Titik ${index + 1}`);
				handle.classList.add('cursor-move');
				handle.addEventListener('pointerdown', (event) => {
					event.preventDefault();
					event.stopPropagation();
					selectedPointIndex = index;
					draggedPointIndex = index;
					svg.setPointerCapture(event.pointerId);
				});
				handle.addEventListener('keydown', (event) => {
					if (event.key === 'Delete' || event.key === 'Backspace') {
						selectedPointIndex = index;
						removeSelectedPoint();
					}
				});
				handles.append(handle);
			});
		};
		const syncShape = () => {
			svg.querySelectorAll('[data-site-plan-area-shape]').forEach((shape) => {
				const isSelected = selectedArea !== null && shape.dataset.areaId === String(selectedArea.id);
				shape.setAttribute('stroke-width', isSelected ? '0.5' : '0.25');
				shape.setAttribute('fill-opacity', isSelected ? '0.55' : '0.3');
			});
		};
		const selectArea = (shape) => {
			drawingNewArea = false;
			selectedArea = {
				id: shape.dataset.areaId,
				lotId: shape.dataset.lotId ?? '',
				label: shape.dataset.label ?? '',
				points: JSON.parse(shape.dataset.points ?? '[]'),
				updateUrl: shape.dataset.updateUrl,
				deleteUrl: shape.dataset.deleteUrl,
			};
			points = selectedArea.points.map((point) => ({ x: Number(point.x), y: Number(point.y) }));
			form.action = selectedArea.updateUrl;
			methodField.value = 'PUT';
			lotSelect.value = selectedArea.lotId;
			labelInput.value = selectedArea.label;
			labelField.value = selectedArea.label;
			deleteForm.action = selectedArea.deleteUrl;
			deleteForm.classList.remove('hidden');
			syncShape();
			renderPoints();
			setStatus(`Area tersimpan dipilih: ${shape.dataset.areaLabel}. Seret titik untuk mengubah batas.`);
		};
		const addPoint = (point) => {
			points.push(point);
			renderPoints();
			setStatus(`${points.length} titik. Minimal 3 titik untuk membentuk poligon.`);
		};
		const insertNearestPoint = (point) => {
			if (points.length < 2) {
				addPoint(point);
				return;
			}

			let nearestIndex = 0;
			let nearestDistance = Number.POSITIVE_INFINITY;
			let nearestPoint = point;

			points.forEach((start, index) => {
				const end = points[(index + 1) % points.length];
				const dx = end.x - start.x;
				const dy = end.y - start.y;
				const lengthSquared = dx * dx + dy * dy;
				const position = lengthSquared === 0
					? 0
					: Math.min(1, Math.max(0, ((point.x - start.x) * dx + (point.y - start.y) * dy) / lengthSquared));
				const projected = { x: start.x + position * dx, y: start.y + position * dy };
				const distance = Math.hypot(point.x - projected.x, point.y - projected.y);

				if (distance < nearestDistance) {
					nearestDistance = distance;
					nearestIndex = index + 1;
					nearestPoint = projected;
				}
			});

			points.splice(nearestIndex, 0, nearestPoint);
			renderPoints();
			setStatus('Titik baru disisipkan pada sisi terdekat.');
		};
		const removeSelectedPoint = () => {
			if (points.length <= 3) {
				setStatus('Poligon harus memiliki sedikitnya 3 titik.');
				return;
			}

			const pointIndex = selectedPointIndex ?? points.length - 1;
			points.splice(pointIndex, 1);
			selectedPointIndex = null;
			renderPoints();
			setStatus('Titik dihapus. Simpan untuk menerapkan perubahan.');
		};
		const setMode = (nextMode) => {
			mode = nextMode;
			form.querySelectorAll('[data-site-plan-mode]').forEach((button) => {
				button.setAttribute('aria-pressed', String(button.dataset.sitePlanMode === mode));
			});
			setStatus(mode === 'pan'
				? 'Geser gambar dengan drag pada area kosong.'
				: mode === 'insert'
					? 'Klik poligon aktif untuk menyisipkan titik pada sisi terdekat.'
					: mode === 'draw'
						? 'Klik gambar untuk menambah titik pada poligon aktif.'
						: 'Pilih poligon untuk mengubah batasnya.');
		};
		const cancelChanges = () => {
			const selectedShape = selectedArea
				? svg.querySelector(`[data-site-plan-area-shape][data-area-id="${CSS.escape(selectedArea.id)}"]`)
				: null;

			if (selectedShape) {
				selectArea(selectedShape);
				setStatus('Perubahan dibatalkan; batas area dikembalikan ke versi tersimpan.');
				return;
			}

			points = [];
			drawingNewArea = true;
			activeLegacyShape = null;
			renderPoints();
			lotSelect.value = '';
			labelInput.value = '';
			labelField.value = '';
			setStatus('Perubahan dibatalkan.');
		};
		const applyZoom = () => {
			if (! zoomContent || ! zoomLabel) {
				return;
			}

			zoomContent.style.width = `${zoom * 100}%`;
			zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
		};

		form.querySelector('[data-site-plan-area-new]')?.addEventListener('click', () => {
			selectedArea = null;
			drawingNewArea = true;
			activeLegacyShape = null;
			activeLegacyShape = null;
			points = [];
			selectedPointIndex = null;
			form.action = form.dataset.storeUrl;
			methodField.value = '';
			lotSelect.value = '';
			labelInput.value = '';
			labelField.value = '';
			deleteForm.classList.add('hidden');
			syncShape();
			renderPoints();
			setMode('draw');
			setStatus('Area baru: klik minimal 3 titik di sekeliling satu petak.');
		});
		form.querySelectorAll('[data-site-plan-mode]').forEach((button) => {
			button.addEventListener('click', () => setMode(button.dataset.sitePlanMode));
		});
		form.querySelector('[data-site-plan-undo]')?.addEventListener('click', () => {
			points.pop();
			renderPoints();
			setStatus('Titik terakhir dibatalkan.');
		});
		form.querySelector('[data-site-plan-remove-point]')?.addEventListener('click', removeSelectedPoint);
		form.querySelector('[data-site-plan-cancel]')?.addEventListener('click', cancelChanges);
		labelInput.addEventListener('input', () => {
			labelField.value = labelInput.value;
		});
		lotSelect.addEventListener('change', () => {
			const option = lotSelect.selectedOptions[0];
			if (option?.dataset.label && ! labelInput.value) {
				labelInput.value = option.dataset.label;
				labelField.value = option.dataset.label;
			}
		});

		svg.querySelectorAll('[data-site-plan-area-shape]').forEach((shape) => {
			shape.addEventListener('click', (event) => {
				event.preventDefault();
				if (mode === 'insert' && selectedArea?.id === shape.dataset.areaId) {
					insertNearestPoint(pointFromEvent(event));
					return;
				}
				if (mode === 'draw' && selectedArea?.id === shape.dataset.areaId) {
					addPoint(pointFromEvent(event));
					return;
				}

				selectArea(shape);
			});
			shape.addEventListener('keydown', (event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					selectArea(shape);
				}
			});
		});
		svg.querySelectorAll('[data-site-plan-legacy-area]').forEach((shape) => {
			const convertLegacyArea = (event) => {
				if (activeLegacyShape === shape) {
					if (mode === 'insert') {
						insertNearestPoint(pointFromEvent(event));
					} else if (mode === 'draw') {
						addPoint(pointFromEvent(event));
					}
					return;
				}

				selectedArea = null;
				drawingNewArea = true;
				activeLegacyShape = shape;
				points = JSON.parse(shape.dataset.points ?? '[]');
				form.action = form.dataset.storeUrl;
				methodField.value = '';
				lotSelect.value = shape.dataset.lotId ?? '';
				labelInput.value = shape.dataset.label ?? '';
				labelField.value = labelInput.value;
				deleteForm.classList.add('hidden');
				syncShape();
				renderPoints();
				setMode('draw');
				setStatus('Kotak pemetaan lama dipilih. Periksa batasnya lalu simpan untuk mengonversi ke poligon.');
			};

			shape.addEventListener('click', convertLegacyArea);
			shape.addEventListener('keydown', (event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					convertLegacyArea();
				}
			});
		});

		svg.addEventListener('click', (event) => {
			if (mode === 'draw' && (drawingNewArea || selectedArea !== null) && event.target === svg) {
				addPoint(pointFromEvent(event));
			}
		});
		svg.addEventListener('pointermove', (event) => {
			if (draggedPointIndex !== null) {
				points[draggedPointIndex] = pointFromEvent(event);
				renderPoints();
			}
		});
		svg.addEventListener('pointerup', () => {
			if (draggedPointIndex !== null) {
				setStatus('Titik poligon diperbarui. Simpan untuk menerapkan perubahan.');
			}
			draggedPointIndex = null;
		});
		viewport?.addEventListener('pointerdown', (event) => {
			if (mode !== 'pan' || event.button !== 0 || event.target.closest('[data-site-plan-area-shape], [data-site-plan-point-handle]')) {
				return;
			}

			panStart = { x: event.clientX, y: event.clientY, left: viewport.scrollLeft, top: viewport.scrollTop };
			viewport.setPointerCapture(event.pointerId);
		});
		viewport?.addEventListener('pointermove', (event) => {
			if (! panStart) {
				return;
			}

			viewport.scrollLeft = panStart.left - (event.clientX - panStart.x);
			viewport.scrollTop = panStart.top - (event.clientY - panStart.y);
		});
		viewport?.addEventListener('pointerup', () => {
			panStart = null;
		});
		form.closest('#site-plan-editor')?.querySelector('[data-site-plan-zoom-in]')?.addEventListener('click', () => {
			zoom = Math.min(4, Number((zoom + 0.25).toFixed(2)));
			applyZoom();
		});
		form.closest('#site-plan-editor')?.querySelector('[data-site-plan-zoom-out]')?.addEventListener('click', () => {
			zoom = Math.max(1, Number((zoom - 0.25).toFixed(2)));
			applyZoom();
		});
		form.closest('#site-plan-editor')?.querySelector('[data-site-plan-zoom-reset]')?.addEventListener('click', () => {
			zoom = 1;
			viewport?.scrollTo({ left: 0, top: 0, behavior: 'smooth' });
			applyZoom();
		});
		form.addEventListener('submit', (event) => {
			labelField.value = labelInput.value;

			if (points.length < 3) {
				event.preventDefault();
				setStatus('Area perlu memiliki minimal 3 titik sebelum disimpan.');
				return;
			}

			if (lotSelect.value === '' && labelInput.value.trim() === '') {
				event.preventDefault();
				setStatus('Isi label area atau hubungkan ke record kavling.');
			}
		});

		const initialAreaId = svg.dataset.initialAreaId;
		const initialArea = initialAreaId
			? svg.querySelector(`[data-site-plan-area-shape][data-area-id="${CSS.escape(initialAreaId)}"]`)
			: null;

		if (initialArea) {
			selectArea(initialArea);
		} else {
			setMode('select');
		}
	});
};

const initializeSitePlanEditors = () => {
	document.querySelectorAll('[data-site-plan-editor]:not([data-site-plan-initialized])').forEach((form) => {
		form.dataset.sitePlanInitialized = 'true';

		const lotSelect = form.querySelector('[data-site-plan-lot-select]');
		const lotPicker = form.querySelector('[data-site-plan-lot-picker]');
		const canvas = form.querySelector('[data-site-plan-canvas]');
		const draft = form.querySelector('[data-site-plan-draft]');
		const drawStatus = form.querySelector('[data-site-plan-draw-status]');
		const saveButton = form.querySelector('[data-site-plan-save]');
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
		let pendingArea = false;
		let overwriteConfirmed = false;
		let isSaving = false;

		if (! lotSelect || ! lotPicker || ! canvas || ! draft || ! saveButton) {
			return;
		}

		const currentOption = () => lotSelect.selectedOptions[0];
		const updateFormAction = () => {
			const option = currentOption();

			if (option?.dataset.url) {
				form.action = option.dataset.url;
			}
		};
		const updateSaveButton = () => {
			saveButton.disabled = isSaving || ! hasArea || ! currentOption()?.value;
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
			if (pendingArea) {
				updateFormAction();
				updateSaveButton();

				return;
			}
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
				lotPicker.hidden = false;
				drawStatus.textContent = 'Kotak tersimpan dimuat. Tarik ulang untuk mengubahnya, atau pilih kavling lain.';
			} else {
				Object.values(coordinateInputs).forEach((input) => {
					input.value = '';
				});
				draft.hidden = true;
				hasArea = false;
				drawStatus.textContent = option?.value
					? 'Kavling dipilih. Tarik kotaknya pada denah.'
					: 'Tarik kotak pada denah untuk mulai memetakan kavling.';
			}
			updateSaveButton();
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

		lotSelect.addEventListener('change', () => {
			updateFormAction();
			loadSelectedArea();
		});
		updateSaveButton();

		canvas.addEventListener('pointerdown', (event) => {
			if (event.button !== 0) {
				return;
			}

			event.preventDefault();
			pendingArea = false;
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
			pendingArea = true;
			lotPicker.hidden = false;
			lotSelect.focus();
			drawStatus.textContent = 'Pilih kavling untuk kotak oranye, lalu simpan.';
			updateSaveButton();
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
			event.preventDefault();

			if (! hasArea) {
				drawStatus.textContent = 'Tandai area kavling pada gambar sebelum menyimpan.';

				return;
			}

			if (! currentOption()?.value) {
				lotPicker.hidden = false;
				drawStatus.textContent = 'Pilih kavling untuk kotak oranye terlebih dahulu.';
				lotSelect.focus();

				return;
			}

			if (overwriteConfirmed) {
				overwriteConfirmed = false;
			} else {
				const option = currentOption();
				const alreadyMarked = ['x', 'y', 'width', 'height']
					.every((key) => option?.dataset[key] !== undefined && option.dataset[key] !== '');

				if (alreadyMarked && confirmDialog) {
					confirmLotLabel.textContent = option.textContent.trim();
					confirmDialog.showModal();

					return;
				}
			}

			isSaving = true;
			updateSaveButton();
			saveButton.textContent = 'Menyimpan…';

			fetch(form.action, {
				method: 'POST',
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: new FormData(form),
				credentials: 'same-origin',
			})
				.then(async (response) => {
					const result = await response.json().catch(() => null);

					if (! response.ok) {
						const validationMessage = Object.values(result?.errors ?? {}).flat()[0];
						throw new Error(validationMessage ?? result?.message ?? `Gagal menyimpan area (${response.status}).`);
					}

					return result;
				})
				.then((result) => {
					if (! result?.lot?.coordinates) {
						throw new Error('Server tidak mengirimkan data area yang tersimpan.');
					}

					const { lot } = result;
					const coordinates = lot.coordinates;
					let box = Array.from(canvas.querySelectorAll('[data-site-plan-mapped-lot]'))
						.find((element) => element.dataset.sitePlanMappedLot === String(lot.id));

					if (! box) {
						box = document.createElement('div');
						box.dataset.sitePlanMappedLot = lot.id;
						box.setAttribute('aria-hidden', 'true');
						box.className = 'pointer-events-none absolute border border-blue-500 bg-blue-500/20';
						canvas.insertBefore(box, draft);
					}

					box.style.left = `${coordinates.x}%`;
					box.style.top = `${coordinates.y}%`;
					box.style.width = `${coordinates.width}%`;
					box.style.height = `${coordinates.height}%`;

					const selectedOption = currentOption();
					selectedOption.dataset.x = coordinates.x;
					selectedOption.dataset.y = coordinates.y;
					selectedOption.dataset.width = coordinates.width;
					selectedOption.dataset.height = coordinates.height;
					selectedOption.textContent = `${lot.label} · sudah ditandai`;

					const editor = form.closest('#site-plan-editor');
					const mappedCount = editor.querySelector('[data-site-plan-mapped-count]');
					if (mappedCount) {
						mappedCount.textContent = result.mapped_lot_count;
					}

					const blockCount = editor.querySelector(`[data-site-plan-block-count="${lot.block_id}"]`);
					if (blockCount) {
						blockCount.textContent = `${result.mapped_block_count} / ${blockCount.dataset.blockTotal} ditandai`;
						blockCount.classList.toggle('text-emerald-700', result.mapped_block_count === Number(blockCount.dataset.blockTotal));
						blockCount.classList.toggle('text-slate-500', result.mapped_block_count !== Number(blockCount.dataset.blockTotal));
					}

					draft.hidden = true;
					lotPicker.hidden = true;
					lotSelect.value = '';
					Object.values(coordinateInputs).forEach((input) => {
						input.value = '';
					});
					hasArea = false;
					pendingArea = false;
					drawStatus.textContent = Number(result.mapped_lot_count) === Number(result.total_lot_count)
						? 'Semua kavling sudah ditandai.'
						: `${result.message} Gambar kotak berikutnya.`;
				})
				.catch((error) => {
					console.error('Site plan mapping failed:', error);
					drawStatus.textContent = error.message || 'Gagal menyimpan area. Periksa koneksi lalu coba lagi.';
				})
				.finally(() => {
					isSaving = false;
					saveButton.textContent = 'Simpan Area Kavling';
					updateSaveButton();
				});
		});
	});
};

initializeCurrencyInputs();
initializeLotImageInputs();
initializeLotFilters();
initializeWorkflowBookingModals();
initializeSitePlanViewers();
initializeSitePlanPolygonEditors();
initializeSitePlanEditors();
initializeAlertNotificationCount();
document.addEventListener('livewire:navigated', () => {
	initializeCurrencyInputs();
	initializeLotImageInputs();
	initializeLotFilters();
	initializeWorkflowBookingModals();
	initializeSitePlanViewers();
	initializeSitePlanPolygonEditors();
	initializeSitePlanEditors();
	initializeAlertNotificationCount();
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
