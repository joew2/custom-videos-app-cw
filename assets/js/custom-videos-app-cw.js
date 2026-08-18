(function () {
	'use strict';

	var modal;
	var frame;
	var lastTrigger;

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

		if (trigger) {
			openModal(trigger);
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && modal && modal.classList.contains('cvacw-modal-is-open')) {
			closeModal();
		}
	});
})();
