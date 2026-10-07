'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const classifier = require('../public/js/epd-attribution-classifier.js');

const sources = {
	google: { family: 'search', aliases: ['google', 'googleads', 'google_ads', 'adwords'] },
	bing: { family: 'search', aliases: ['bing', 'microsoft', 'microsoft_ads'] },
	instagram: { family: 'social', aliases: ['instagram', 'ig', 'insta'] },
	facebook: { family: 'social', aliases: ['facebook', 'fb', 'meta', 'meta_ads'] },
	tiktok: { family: 'social', aliases: ['tiktok', 'tik_tok'] },
	linkedin: { family: 'social', aliases: ['linkedin', 'linked_in'] }
};

function classify(overrides) {
	return classifier.classify(Object.assign({
		search: '',
		referrer: '',
		currentHost: 'www.example.com',
		siteHost: 'example.com',
		sources
	}, overrides || {}));
}

test('preserva UTMs explícitas e classifica CPC como tráfego pago', () => {
	const result = classify({ search: '?utm_source=facebook&utm_medium=cpc&utm_campaign=black-friday' });
	assert.equal(result.utm_source, 'facebook');
	assert.equal(result.utm_medium, 'cpc');
	assert.equal(result.utm_campaign, 'black-friday');
	assert.equal(result.utm_channel, 'Paid Social');
	assert.equal(result.attribution_method, 'utm');
});

test('classifica CPC de buscador como Paid Search', () => {
	const result = classify({ search: '?utm_source=google&utm_medium=cpc' });
	assert.equal(result.utm_channel, 'Paid Search');
});

test('classifica Meta com CPC como Paid Social', () => {
	const result = classify({ search: '?utm_source=meta&utm_medium=cpc' });
	assert.equal(result.normalized_source, 'facebook');
	assert.equal(result.utm_channel, 'Paid Social');
});

test('infere Google Ads pelo gclid', () => {
	const result = classify({ search: '?gclid=abc123' });
	assert.equal(result.utm_source, 'google');
	assert.equal(result.utm_medium, 'cpc');
	assert.equal(result.utm_channel, 'Paid Search');
	assert.equal(result.attribution_method, 'click_id');
});

test('infere TikTok Ads pelo ttclid como Paid Social', () => {
	const result = classify({ search: '?ttclid=tiktok123' });
	assert.equal(result.utm_source, 'tiktok');
	assert.equal(result.utm_medium, 'cpc');
	assert.equal(result.utm_channel, 'Paid Social');
});

test('infere busca orgânica pelo referrer', () => {
	const result = classify({ referrer: 'https://www.google.com/search?q=produto' });
	assert.equal(result.utm_source, 'google');
	assert.equal(result.utm_medium, 'organic');
	assert.equal(result.utm_channel, 'Organic Search');
});

test('infere rede social orgânica pelo referrer', () => {
	const result = classify({ referrer: 'https://www.instagram.com/' });
	assert.equal(result.utm_source, 'instagram');
	assert.equal(result.utm_medium, 'social');
	assert.equal(result.utm_channel, 'Organic Social');
});

test('infere referral para domínio externo desconhecido', () => {
	const result = classify({ referrer: 'https://partner.example.net/article' });
	assert.equal(result.utm_source, 'partner.example.net');
	assert.equal(result.utm_medium, 'referral');
	assert.equal(result.utm_channel, 'Referral');
});

test('não trata subdomínio próprio como referral', () => {
	const result = classify({
		currentHost: 'app.example.com',
		referrer: 'https://blog.example.com/article'
	});
	assert.equal(result.utm_source, '(direct)');
	assert.equal(result.utm_medium, '(none)');
	assert.equal(result.utm_channel, 'Direct');
});

test('preenche UTM parcial usando click ID', () => {
	const result = classify({ search: '?utm_campaign=spring&msclkid=ms123' });
	assert.equal(result.utm_source, 'bing');
	assert.equal(result.utm_medium, 'cpc');
	assert.equal(result.utm_campaign, 'spring');
	assert.equal(result.utm_channel, 'Paid Search');
});

test('mantém first touch e não substitui last touch por acesso direto', () => {
	const first = classify({ referrer: 'https://www.google.com/search?q=produto' });
	const initial = classifier.merge({}, first, sources);
	const direct = classify();
	const merged = classifier.merge(initial, direct, sources);

	assert.equal(merged.first.utm_source, 'google');
	assert.equal(merged.last.utm_source, 'google');
});

test('atualiza last touch quando surge nova origem não direta', () => {
	const google = classify({ referrer: 'https://www.google.com/search?q=produto' });
	const initial = classifier.merge({}, google, sources);
	const linkedin = classify({ referrer: 'https://www.linkedin.com/feed/' });
	const merged = classifier.merge(initial, linkedin, sources);

	assert.equal(merged.first.utm_source, 'google');
	assert.equal(merged.last.utm_source, 'linkedin');
});
