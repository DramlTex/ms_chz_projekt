/**
 * Простое хранилище состояния страницы заказа КМ.
 * Основано на EventTarget, чтобы подписывать UI-модули на изменения.
 */

class OrderStore extends EventTarget {
  constructor() {
    super();
    this.state = {
      cards: [],
      total: 0,
      meta: {
        source: '—',
        fallback: false,
        rawTotal: 0,
      },
      filters: {},
      selected: new Map(),
      loading: false,
      lastUpdated: null,
    };
  }

  /**
   * Обновляет признак загрузки и уведомляет подписчиков.
   * @param {boolean} value
   */
  setLoading(value) {
    if (this.state.loading === value) return;
    this.state.loading = value;
    this.dispatchEvent(
      new CustomEvent('loading-changed', {
        detail: { loading: value },
      }),
    );
  }

  /**
   * Сохраняет карточки и метаданные запроса.
   * @param {Array<object>} cards
   * @param {{ total?: number; source?: string; fallback?: boolean; rawTotal?: number; fetchedAt?: Date }} meta
   */
  setCards(cards, meta = {}) {
    this.state.cards = Array.isArray(cards) ? cards : [];
    const validIds = new Set(this.state.cards.map((card) => card.goodId));
    Array.from(this.state.selected.keys()).forEach((id) => {
      if (!validIds.has(id)) {
        this.state.selected.delete(id);
      }
    });

    this.state.total = Number.isFinite(meta.total) ? meta.total : this.state.cards.length;
    this.state.meta = {
      ...this.state.meta,
      source: meta.source ?? this.state.meta.source,
      fallback: Boolean(meta.fallback),
      rawTotal: Number.isFinite(meta.rawTotal) ? meta.rawTotal : this.state.total,
    };
    this.state.lastUpdated = meta.fetchedAt ?? new Date();

    this.dispatchEvent(
      new CustomEvent('cards-changed', {
        detail: {
          cards: this.getCards(),
          meta: this.getMeta(),
          total: this.state.total,
        },
      }),
    );
    this._emitSelection();
  }

  /**
   * Обновляет выбранные карточки, добавляя или исключая указанную.
   * @param {string} goodId
   */
  toggleSelection(goodId) {
    if (!goodId) return;
    if (this.state.selected.has(goodId)) {
      this.state.selected.delete(goodId);
    } else {
      const card = this.state.cards.find((item) => item.goodId === goodId);
      if (card) {
        this.state.selected.set(goodId, { ...card });
      }
    }
    this._emitSelection();
  }

  /**
   * Массово выбирает/снимает карточки на текущей странице.
   * @param {string[]} goodIds
   * @param {boolean} shouldSelect
   */
  setBulkSelection(goodIds, shouldSelect) {
    if (!Array.isArray(goodIds) || goodIds.length === 0) return;
    const validIds = new Set(this.state.cards.map((card) => card.goodId));
    let changed = false;
    goodIds.forEach((id) => {
      if (!validIds.has(id)) return;
      if (shouldSelect) {
        const card = this.state.cards.find((item) => item.goodId === id);
        if (card && !this.state.selected.has(id)) {
          this.state.selected.set(id, { ...card });
          changed = true;
        }
      } else if (this.state.selected.has(id)) {
        this.state.selected.delete(id);
        changed = true;
      }
    });
    if (changed) {
      this._emitSelection();
    }
  }

  /**
   * Очищает выбор полностью.
   */
  clearSelection() {
    if (this.state.selected.size === 0) return;
    this.state.selected.clear();
    this._emitSelection();
  }

  /**
   * Сохраняет используемые фильтры.
   * @param {object} filters
   */
  setFilters(filters) {
    this.state.filters = {
      ...this.state.filters,
      ...filters,
    };
    this.dispatchEvent(
      new CustomEvent('filters-changed', {
        detail: { filters: this.getFilters() },
      }),
    );
  }

  /** Возвращает актуальные фильтры. */
  getFilters() {
    return { ...this.state.filters };
  }

  /** Возвращает список карточек. */
  getCards() {
    return this.state.cards.map((card) => ({ ...card }));
  }

  /** Возвращает массив выбранных карточек. */
  getSelectedCards() {
    return Array.from(this.state.selected.values()).map((card) => ({ ...card }));
  }

  /** Возвращает мета-информацию последнего запроса. */
  getMeta() {
    return { ...this.state.meta };
  }

  /** Возвращает количество выбранных карточек. */
  getSelectionSize() {
    return this.state.selected.size;
  }

  /** Проверяет, выбрана ли карточка. */
  isSelected(goodId) {
    return this.state.selected.has(goodId);
  }

  _emitSelection() {
    this.dispatchEvent(
      new CustomEvent('selection-changed', {
        detail: {
          selected: this.getSelectedCards(),
          totalSelected: this.state.selected.size,
        },
      }),
    );
  }
}

export const orderStore = new OrderStore();
