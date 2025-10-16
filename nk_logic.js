/**
 * НК Logic - Логика работы с Национальным Каталогом
 * ИСПРАВЛЕНО: Все запросы идут через api.php прокси для обхода CORS
 */

const NKLogic = {
    // Маппинг стран
    COUNTRY_MAP: {
        'россия': 'RU',
        'russia': 'RU',
        'рф': 'RU',
        'китай': 'CN',
        'china': 'CN',
        'турция': 'TR',
        'turkey': 'TR',
        'беларусь': 'BY',
        'belarus': 'BY',
        'казахстан': 'KZ',
        'kazakhstan': 'KZ'
    },

    /**
     * Главная функция - создать карточку НК
     */
    async createCard(product, msToken, nkToken, options = {}) {
        try {
            // 1. Извлечь данные товара
            console.log('Извлечение данных товара...');
            const productData = await this.extractProductData(product, msToken);
            
            // 2. Определить категорию по ТН ВЭД
            console.log('Определение категории...');
            const categoryInfo = await this.detectCategory(productData.tnved, nkToken);
            
            // 3. Получить технический GTIN (если нужен)
            if (options.isTechGtin !== false) {
                console.log('Получение технического GTIN...');
                productData.gtin = await this.getTechGtin(nkToken);
            }
            
            // 4. Сформировать данные карточки
            console.log('Формирование карточки...');
            const cardData = this.createCardData(productData, categoryInfo, options);
            
            // 5. Отправить карточку в НК
            console.log('Отправка карточки в НК...');
            const feedId = await this.sendCardToNK(cardData, nkToken);
            
            // 6. Ждать результата обработки
            console.log('Ожидание обработки карточки...');
            const result = await this.waitForFeedStatus(feedId, nkToken);
            
            if (!result.success) {
                throw new Error('Карточка отклонена: ' + (result.errors || 'Неизвестная ошибка'));
            }
            
            // 7. Обновить GTIN в МойСклад (если нужно)
            if (options.updateMS !== false && result.gtin) {
                console.log('Обновление GTIN в МойСклад...');
                await this.updateMSGtin(product.id, result.gtin, msToken);
            }
            
            return {
                success: true,
                gtin: result.gtin,
                feedId: feedId,
                cardData: cardData
            };
            
        } catch (error) {
            console.error('Ошибка создания карточки:', error);
            throw error;
        }
    },

    /**
     * Извлечь данные товара из МойСклад
     */
    async extractProductData(product, msToken) {
        const data = {
            id: product.id,
            name: product.name,
            article: product.article || product.code || '',
            description: product.description || '',
            tnved: null,
            country: 'RU',
            brand: '',
            color: '',
            size: '',
            composition: [],
            documents: [],
            attributes: {}
        };

        // Извлечь атрибуты
        if (product.attributes) {
            for (const attr of product.attributes) {
                const attrName = (attr.name || '').toLowerCase();
                const attrValue = attr.value?.name || attr.value || '';

                data.attributes[attr.name] = attrValue;

                // ТН ВЭД
                if (attrName.includes('тн вэд') || attrName.includes('тнвэд') || attrName === 'tnved') {
                    data.tnved = this.extractTNVED(attrValue);
                }

                // Страна
                if (attrName.includes('страна')) {
                    data.country = this.normalizeCountry(attrValue);
                }

                // Бренд
                if (attrName.includes('бренд') || attrName.includes('производитель')) {
                    data.brand = attrValue;
                }

                // Цвет
                if (attrName.includes('цвет')) {
                    data.color = attrValue;
                }

                // Размер
                if (attrName.includes('размер') || attrName === 'size') {
                    data.size = attrValue;
                }

                // Состав
                if (attrName.includes('состав')) {
                    data.composition.push({
                        material: attrValue,
                        percentage: 100
                    });
                }

                // Документы
                if (attrName.includes('документ') || attrName.includes('сертификат')) {
                    data.documents.push({
                        name: attr.name,
                        value: attrValue
                    });
                }
            }
        }

        // Если нет ТН ВЭД - ошибка
        if (!data.tnved) {
            throw new Error('У товара не указан ТН ВЭД код');
        }

        return data;
    },

    /**
     * Извлечь ТН ВЭД код
     */
    extractTNVED(value) {
        if (!value) return null;
        
        const digits = value.toString().replace(/\D/g, '');
        
        if (digits.length >= 4) {
            return digits.substring(0, Math.min(10, digits.length));
        }
        
        return null;
    },

    /**
     * Нормализовать название страны
     */
    normalizeCountry(value) {
        if (!value) return 'RU';
        
        const normalized = value.toLowerCase().trim();
        return this.COUNTRY_MAP[normalized] || 'RU';
    },

    /**
     * Определить категорию по ТН ВЭД
     * ИСПРАВЛЕНО: Использует api.php прокси
     */
    async detectCategory(tnved, nkToken) {
        if (!tnved || tnved.length < 4) {
            throw new Error('ТН ВЭД код должен содержать минимум 4 цифры');
        }

        // ✅ Используем ПРОКСИ вместо прямого запроса!
        const checkResponse = await fetch('api.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'nk_check_feacn',
                tnved: tnved
            })
        });

        if (!checkResponse.ok) {
            const errorText = await checkResponse.text();
            throw new Error('Ошибка проверки ТН ВЭД: ' + errorText);
        }

        const checkData = await checkResponse.json();
        const item = checkData.items?.[0];

        if (!item || !item.is_marked) {
            throw new Error('Товар с данным ТН ВЭД не подлежит маркировке');
        }

        // ✅ Получение категории через ПРОКСИ
        const categoryResponse = await fetch('api.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'nk_get_category',
                tnved: tnved
            })
        });

        if (!categoryResponse.ok) {
            const errorText = await categoryResponse.text();
            throw new Error('Ошибка получения категории: ' + errorText);
        }

        const categories = await categoryResponse.json();
        
        if (!categories || categories.length === 0) {
            throw new Error('Категория не найдена');
        }

        return {
            productGroupCode: item.product_group_code,
            categoryId: categories[0].id
        };
    },

    /**
     * Получить технический GTIN
     * ИСПРАВЛЕНО: Использует api.php прокси
     */
    async getTechGtin(nkToken, quantity = 1) {
        // ✅ Используем ПРОКСИ
        const response = await fetch('api.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'nk_get_gtin',
                quantity: quantity
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error('Ошибка получения GTIN: ' + errorText);
        }

        const data = await response.json();
        
        if (!data.codes || data.codes.length === 0) {
            throw new Error('Не удалось получить GTIN');
        }

        return data.codes[0];
    },

    /**
     * Создать данные карточки НК
     */
    createCardData(productData, categoryInfo, options = {}) {
        const {
            isTechGtin = true,
            moderation = false,
            nameOptions = { 
                includeArticle: true, 
                includeSize: true, 
                includeColor: true 
            }
        } = options;

        // Формируем полное название
        let fullName = productData.name;
        
        if (nameOptions.includeArticle && productData.article) {
            fullName = productData.article + ' ' + fullName;
        }
        
        if (nameOptions.includeSize && productData.size) {
            fullName += ' ' + productData.size;
        }
        
        if (nameOptions.includeColor && productData.color) {
            fullName += ' ' + productData.color;
        }

        // Обрезаем до 500 символов
        if (fullName.length > 500) {
            fullName = fullName.substring(0, 500);
        }

        // Базовая структура карточки
        const card = {
            is_tech_gtin: isTechGtin,
            good_name: fullName,
            moderation: moderation ? 'true' : 'false',
            product_group_code: categoryInfo.productGroupCode,
            good_description: productData.description || fullName,
            tnveds: [productData.tnved],
            attributes: []
        };

        // Добавляем GTIN если есть
        if (productData.gtin) {
            card.gtin = productData.gtin;
        }

        // Добавляем категорию
        if (categoryInfo.categoryId) {
            card.category_id = categoryInfo.categoryId;
        }

        // Атрибуты
        const attributes = [];

        // Страна производства
        attributes.push({
            id: 'country',
            value: productData.country
        });

        // Бренд
        if (productData.brand) {
            attributes.push({
                id: 'brand',
                value: productData.brand
            });
        }

        // Цвет
        if (productData.color) {
            attributes.push({
                id: 'color',
                value: productData.color
            });
        }

        // Размер
        if (productData.size) {
            attributes.push({
                id: 'size',
                value: productData.size
            });
        }

        // Состав
        if (productData.composition.length > 0) {
            attributes.push({
                id: 'composition',
                value: productData.composition
            });
        }

        card.attributes = attributes;

        return card;
    },

    /**
     * Отправить карточку в НК
     * ИСПРАВЛЕНО: Использует api.php прокси
     */
    async sendCardToNK(cardData, nkToken) {
        // ✅ Используем ПРОКСИ
        const response = await fetch('api.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'nk_create_card',
                cardData: cardData
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error('Ошибка отправки карточки: ' + errorText);
        }

        const data = await response.json();
        const feedId = data.result?.feed_id;

        if (!feedId) {
            throw new Error('feed_id не получен из ответа');
        }

        return feedId;
    },

    /**
     * Опросить статус обработки карточки
     * ИСПРАВЛЕНО: Использует api.php прокси
     */
    async waitForFeedStatus(feedId, nkToken, maxRetries = 30, interval = 2000) {
        for (let i = 0; i < maxRetries; i++) {
            // Ждем перед проверкой
            await new Promise(resolve => setTimeout(resolve, interval));

            // ✅ Используем ПРОКСИ
            const response = await fetch('api.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'nk_feed_status',
                    feedId: feedId
                })
            });

            if (!response.ok) {
                console.warn('Ошибка получения статуса, повтор...');
                continue;
            }

            const status = await response.json();

            console.log(`Попытка ${i + 1}/${maxRetries}: статус = ${status.status}`);

            // Успешно обработано
            if (status.status === 'Processed') {
                return {
                    success: true,
                    gtin: status.item?.gtin,
                    status: status.status
                };
            } 
            // Отклонено
            else if (status.status === 'Rejected') {
                const errors = status.errors || [];
                const errorText = errors.map(e => e.error_description || e.message).join('; ');
                
                return {
                    success: false,
                    status: status.status,
                    errors: errorText || 'Карточка отклонена без описания ошибки'
                };
            }
            // Продолжаем ждать
        }

        throw new Error('Превышено время ожидания обработки карточки (60 секунд)');
    },

    /**
     * Обновить GTIN в МойСклад
     */
    async updateMSGtin(productId, gtin, msToken) {
        const response = await fetch('api.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update_gtin',
                productId: productId,
                gtin: gtin
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error('Ошибка обновления GTIN: ' + errorText);
        }

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Неизвестная ошибка при обновлении GTIN');
        }

        return true;
    }
};

// Экспортируем для использования
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NKLogic;
}