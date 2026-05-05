(function () {
	'use strict';

	document.addEventListener('submit', function (event) {
		var form = event.target;

		if (!form.classList || !form.classList.contains('wccm-delete-form')) {
			return;
		}

		var confirmed = window.confirm(wccmAdminConfirmMessage());
		if (!confirmed) {
			event.preventDefault();
		}
	});

	document.addEventListener('DOMContentLoaded', function () {
		bindSuggestedIncreaseControls();
		initWooCommerceProductSearch();
		bindCopyableSecrets();
	});

	function wccmAdminConfirmMessage() {
		if (window.wccmAdmin && window.wccmAdmin.confirmDelete) {
			return window.wccmAdmin.confirmDelete;
		}

		return '';
	}

	function bindSuggestedIncreaseControls() {
		var selectors = document.querySelectorAll('select[name="suggested_increase_limit_mode"], select[name="suggested_increase_mode"]');

		selectors.forEach(function (select) {
			var form = select.closest('form');
			if (!form) {
				return;
			}

			var field = form.querySelector('[data-wccm-suggested-increase-percentage]');
			if (!field) {
				return;
			}

			var updateVisibility = function () {
				var shouldShow = false;

				if (select.name === 'suggested_increase_limit_mode') {
					shouldShow = select.value !== 'none';
				} else {
					shouldShow = select.value === 'percent';
				}

				field.hidden = !shouldShow;
			};

			select.addEventListener('change', updateVisibility);
			updateVisibility();
		});
	}

	function initWooCommerceProductSearch() {
		var fields = document.querySelectorAll('.wccm-product-search');
		var adminConfig = window.wccmAdmin || {};

		if (!fields.length) {
			return;
		}

		initNativeProductSearchFallback(fields, adminConfig);
	}

	function initNativeProductSearchFallback(fields, adminConfig) {
		fields.forEach(function (select) {
			if (select.dataset.wccmNativeSearch === '1') {
				return;
			}

			select.dataset.wccmNativeSearch = '1';

			var originalName = select.getAttribute('name') || 'product_id';
			var selected = select.options[select.selectedIndex];
			var hidden = document.createElement('input');
			var wrapper = document.createElement('div');
			var input = document.createElement('input');
			var results = document.createElement('div');
			var timer = null;

			hidden.type = 'hidden';
			hidden.name = originalName;
			hidden.value = select.value || '';
			select.removeAttribute('name');
			select.required = false;
			select.hidden = true;

			wrapper.className = 'wccm-native-product-search';
			input.type = 'search';
			input.autocomplete = 'off';
			input.placeholder = select.getAttribute('data-placeholder') || adminConfig.searchProductsPlaceholder || 'Search product by name or SKU...';
			input.value = selected && selected.value ? selected.textContent : '';
			results.className = 'wccm-native-product-search-results';
			results.hidden = true;

			wrapper.appendChild(hidden);
			wrapper.appendChild(input);
			wrapper.appendChild(results);
			select.parentNode.insertBefore(wrapper, select.nextSibling);

			input.addEventListener('input', function () {
				hidden.value = '';
				input.setCustomValidity('');
				window.clearTimeout(timer);

				var term = input.value.trim();
				if (term.length < 3) {
					results.hidden = true;
					results.innerHTML = '';
					return;
				}

				timer = window.setTimeout(function () {
					searchProducts(select, adminConfig, term, results, function (id, text) {
						hidden.value = id;
						input.value = text;
						results.hidden = true;
						results.innerHTML = '';
					});
				}, 250);
			});

			var form = select.closest('form');
			if (form) {
				form.addEventListener('submit', function (event) {
					if (!hidden.value) {
						event.preventDefault();
						input.setCustomValidity('Select a WooCommerce product from the search results.');
						input.reportValidity();
					}
				});
			}
		});
	}

	function searchProducts(select, adminConfig, term, results, onSelect) {
		var params = new URLSearchParams({
			action: select.getAttribute('data-action') || 'woocommerce_json_search_products_and_variations',
			security: select.getAttribute('data-security') || adminConfig.searchProductsNonce || '',
			term: term,
			limit: '20'
		});

		results.hidden = false;
		results.innerHTML = '<div class="wccm-native-product-search-message">Searching...</div>';

		window.fetch((adminConfig.ajaxUrl || window.ajaxurl) + '?' + params.toString(), {
			credentials: 'same-origin',
			headers: {
				accept: 'application/json'
			}
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (data) {
				var products = normalizeProductSearchResults(data);

				if (!products.length) {
					results.innerHTML = '<div class="wccm-native-product-search-message">No products found.</div>';
					return;
				}

				results.innerHTML = '';
				products.forEach(function (product) {
					var button = document.createElement('button');
					button.type = 'button';
					button.className = 'wccm-native-product-search-result';
					button.textContent = product.text;
					button.addEventListener('click', function () {
						onSelect(product.id, product.text);
					});
					results.appendChild(button);
				});
			})
			.catch(function () {
				results.innerHTML = '<div class="wccm-native-product-search-message">Product search failed.</div>';
			});
	}

	function normalizeProductSearchResults(data) {
		if (data && Array.isArray(data.results)) {
			return data.results.map(function (item) {
				return {
					id: String(item.id || ''),
					text: String(item.text || item.id || '')
				};
			}).filter(function (item) {
				return item.id && item.text;
			});
		}

		return Object.keys(data || {}).map(function (id) {
			var item = data[id];
			return {
				id: item && item.id ? String(item.id) : String(id),
				text: item && item.text ? String(item.text) : String(item || id)
			};
		}).filter(function (item) {
			return item.id && item.text;
		});
	}

	function bindCopyableSecrets() {
		document.querySelectorAll('.wccm-copyable-secret').forEach(function (input) {
			input.addEventListener('focus', function () {
				input.select();
			});

			input.addEventListener('click', function () {
				input.select();
			});
		});
	}
})();
