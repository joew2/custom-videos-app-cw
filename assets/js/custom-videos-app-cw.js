(function () {
	'use strict';

	var modal;
	var frame;
	var lastTrigger;

	function loadMoreVideos(app, button) {
		var grid = app.querySelector('div.cvacw-video-grid');
		var formData = new FormData();

		if (!grid || button.disabled) {
			return;
		}

		button.disabled = true;
		button.classList.add('cvacw-load-more-is-loading');
		button.textContent = 'Loading...';

		formData.append('action', 'cvacw_load_more_videos');
		formData.append('nonce', app.getAttribute('data-nonce') || '');
		formData.append('page', app.getAttribute('data-next-page') || '1');
		formData.append('per_page', app.getAttribute('data-per-page') || '12');
		formData.append('show_description', app.getAttribute('data-show-description') || '0');

		fetch(app.getAttribute('data-ajax-url') || '', {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (response) {
				var data;

				if (!response || !response.success) {
					throw new Error(response && response.data && response.data.message ? response.data.message : 'Unable to load videos.');
				}

				data = response.data || {};

				if (data.html) {
					grid.insertAdjacentHTML('beforeend', data.html);
				}

				app.setAttribute('data-next-page', data.nextPage ? String(data.nextPage) : '0');

				if (!data.hasMore) {
					if (button.parentNode && button.parentNode.classList.contains('cvacw-load-more-wrap')) {
						button.parentNode.parentNode.removeChild(button.parentNode);
					} else if (button.parentNode) {
						button.parentNode.removeChild(button);
					}
					return;
				}

				button.disabled = false;
				button.classList.remove('cvacw-load-more-is-loading');
				button.textContent = 'Load more';
			})
			.catch(function () {
				button.disabled = false;
				button.classList.remove('cvacw-load-more-is-loading');
				button.textContent = 'Try again';
			});
	}

	function getModal() {
		if (modal) {
			return modal;
		}

		modal = document.createElement('div');
		modal.className = 'cvacw-modal';
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-label', 'Video player');
		modal.innerHTML = '<div class="cvacw-modal__dialog"><button type="button" class="cvacw-modal__close" aria-label="Close video">&times;</button><div class="cvacw-modal__frame"></div></div>';
		document.body.appendChild(modal);

		frame = modal.querySelector('div.cvacw-modal__frame');
		modal.querySelector('button.cvacw-modal__close').addEventListener('click', closeModal);
		modal.addEventListener('click', function (event) {
			if (event.target === modal) {
				closeModal();
			}
		});

		return modal;
	}

	function openModal(trigger) {
		var url = trigger.getAttribute('data-vimeo-url');
		var title = trigger.getAttribute('data-vimeo-title') || 'Vimeo video';
		var embedUrl;

		if (!url) {
			return;
		}

		try {
			embedUrl = new URL(url, window.location.href);
			embedUrl.searchParams.set('autoplay', '1');
			embedUrl.searchParams.set('title', '0');
			embedUrl.searchParams.set('byline', '0');
			embedUrl.searchParams.set('portrait', '0');
		} catch (error) {
			return;
		}

		lastTrigger = trigger;
		getModal();
		frame.innerHTML = '<iframe class="cvacw-modal__iframe" src="' + embedUrl.toString() + '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="' + escapeAttribute(title) + '"></iframe>';
		modal.classList.add('cvacw-modal-is-open');
		modal.querySelector('button.cvacw-modal__close').focus();
	}

	function closeModal() {
		if (!modal) {
			return;
		}

		modal.classList.remove('cvacw-modal-is-open');
		frame.innerHTML = '';

		if (lastTrigger) {
			lastTrigger.focus();
		}
	}

	function escapeAttribute(value) {
		return value.replace(/[&<>"']/g, function (character) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			}[character];
		});
	}

	document.addEventListener('click', function (event) {
		var trigger = event.target.closest('button.cvacw-video-trigger');
		var loadMoreButton = event.target.closest('button.cvacw-load-more');
		var app;

		if (trigger) {
			openModal(trigger);
		}

		if (loadMoreButton) {
			app = loadMoreButton.closest('div.cvacw-video-app');

			if (app) {
				loadMoreVideos(app, loadMoreButton);
			}
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && modal && modal.classList.contains('cvacw-modal-is-open')) {
			closeModal();
		}
	});
})();
