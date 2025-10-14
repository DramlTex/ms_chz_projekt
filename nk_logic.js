/**
 * НК Logic Module
 * Логика создания карточек в Национальном каталоге
 * Адаптировано из Flask проекта
 */

const NKLogic = {
    /**
     * Карта стран: русское название → ISO-2 код
     */
    COUNTRY_MAP: {
        'россия': 'RU',
        'российская федерация': 'RU',
        'рф': 'RU',
        'китай': 'CN',
        'турция': 'TR',
        'казахстан': 'KZ',
        'беларусь': 'BY',
        'узбекистан': 'UZ',
        'индия': 'IN',
        'бангладеш': 'BD',
        'вьетнам': 'VN'
    },

    /**
     * Категории, требующие полный 10-значный ТН ВЭД
     */
    CATEGORIES_WITH_FULL_TNVED: new Set([
        30933, // Одежда
        // Добавьте другие категории по необходимости
    ]),

    /**
     * ID атрибутов НК
     */
    ATTR_IDS: {
        COUNTRY: 2630,        // Страна производства
        PRODUCT_NAME: 2478,   // Наименование
        BRAND: 2504,          // Бренд
        TNVED_DETAILED: 13933, // Детальный ТН ВЭД
        PRODUCT_KIND: 12,     // Вид товара
        COLOR: 36,            // Цвет
        SIZE: 35,             // Размер
        COMPOSITION: 2483,    // Состав
        CERT: 13836,          // Документ
        ARTICLE: 13914,       // Артикул
        GENDER: 14013         // Целевой пол
    },

    /**
     * Привести значение атрибута к строке
     */
    formatAttributeValue(value) {
        if (value === null || value === undefined) {
            return null;
        }

        if (typeof value === 'object') {
            if (Array.isArray(value)) {
                const items = value
                    .map(item => this.formatAttributeValue(item))
                    .filter(Boolean);
                return items.length ? items.join(', ') : null;
            }

            if (typeof value.name === 'string') {
                return value.name;
            }

            if (typeof value.value !== 'undefined') {
                return this.formatAttributeValue(value.value);
            }

            if (typeof value.title === 'string') {
                return value.title;
            }

            if (typeof value.label === 'string') {
                return value.label;
            }

            return null;
        }

        if (typeof value === 'boolean') {
            return value ? 'Да' : 'Нет';
        }

        return String(value).trim();
    },

    /**
     * Извлечь данные товара с наследованием от родителя
     */
    async extractProductData(product, msToken) {
        const data = {
            name: product.name,
            article: product.article || null,
            code: product.code || null,
            tnved: this.extractTNVED(product.tnved) || null,
            country: null,
            countryName: null,
            brand: null,
            color: null,
            size: null,
            composition: null,
            documents: [],
            gender: null,
            productKind: null,
            sizeType: null,
            documentType: null,
            documentNumber: null,
            documentDate: null
        };

        // Если есть атрибуты, извлекаем
        if (product.attributes) {
            for (const attr of product.attributes) {
                const attrName = attr.name?.toLowerCase() || '';
                const attrValue = this.formatAttributeValue(attr.value);

                if (!attrValue) {
                    continue;
                }

                if (attrName.includes('тн вэд') || attrName.includes('тнвэд')) {
                    data.tnved = this.extractTNVED(attrValue);
                } else if (attrName.includes('страна')) {
                    data.country = this.normalizeCountry(attrValue);
                    data.countryName = attrValue;
                } else if (attrName.includes('бренд') || attrName.includes('торговая марка')) {
                    data.brand = attrValue;
                } else if (attrName.includes('цвет')) {
                    data.color = attrValue;
                } else if (attrName.includes('размер')) {
                    if (attrName.includes('вид размера') || attrName.includes('тип размера')) {
                        data.sizeType = attrValue;
                    } else {
                        data.size = attrValue;
                    }
                } else if (attrName.includes('состав')) {
                    data.composition = attrValue;
                } else if (attrName.includes('пол')) {
                    data.gender = attrValue;
                } else if (attrName.includes('вид товара')) {
                    data.productKind = attrValue;
                } else if (attrName.includes('тип товара')) {
                    data.productKind = attrValue;
                } else if (attrName.includes('вид документа') || attrName.includes('тип документа')) {
                    data.documentType = attrValue;
                    data.documents.push({
                        name: attr.name,
                        type: attrValue,
                        value: attrValue
                    });
                } else if (attrName.includes('номер документа') || attrName.includes('сертификат') || attrName.includes('декларац')) {
                    if (!data.documentNumber) {
                        data.documentNumber = attrValue;
                    }
                    data.documents.push({
                        name: attr.name,
                        number: attrValue,
                        value: attrValue
                    });
                } else if (attrName.includes('дата документа')) {
                    data.documentDate = attrValue;
                    data.documents.push({
                        name: attr.name,
                        date: attrValue,
                        value: attrValue
                    });
                } else if (attrName.includes('документ')) {
                    data.documents.push({
                        name: attr.name,
                        value: attrValue
                    });
                }
            }
        }

        return data;
    },

    /**
     * Извлечь ТН ВЭД код
     */
    extractTNVED(value) {
        if (!value) return null;
        
        // Убираем все кроме цифр
        const digits = value.toString().replace(/\D/g, '');
        
        // Берем первые 4-10 цифр
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
     */
    async detectCategory(tnved, nkToken) {
        if (!tnved || tnved.length < 4) {
            throw new Error('ТН ВЭД код должен содержать минимум 4 цифры');
        }

        // Проверка маркируемости
        const checkResponse = await fetch('https://markirovka.crpt.ru/api/v3/check/feacn', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + nkToken
            },
            body: JSON.stringify({
                feacn: [tnved.substring(0, 4)]
            })
        });

        if (!checkResponse.ok) {
            throw new Error('Ошибка проверки ТН ВЭД');
        }

        const checkData = await checkResponse.json();
        const item = checkData.items?.[0];

        if (!item || !item.is_marked) {
            throw new Error('Товар с данным ТН ВЭД не подлежит маркировке');
        }

        // Получение категории
        const categoryResponse = await fetch(
            'https://markirovka.crpt.ru/api/v3/categories/by-feacn?feacn=' + tnved.substring(0, 4),
            {
                headers: {
                    'Authorization': 'Bearer ' + nkToken
                }
            }
        );

        if (!categoryResponse.ok) {
            throw new Error('Ошибка получения категории');
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
     * Создать данные карточки НК
     */
    createCardData(productData, categoryInfo, options = {}) {
        const {
            isTechGtin = true,
            moderation = false,
            nameOptions = { includeArticle: true, includeSize: true, includeColor: true }
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

        // Базовая структура карточки
        const card = {
            is_tech_gtin: isTechGtin,
            good_name: fullName,
            moderation: moderation ? 1 : 0,
            categories: [categoryInfo.categoryId],
            good_attrs: []
        };

        // Добавляем ТН ВЭД
        if (productData.tnved) {
            card.tnved = productData.tnved.substring(0, 4);
            
            // Если категория требует полный ТН ВЭД
            if (this.CATEGORIES_WITH_FULL_TNVED.has(categoryInfo.categoryId)) {
                if (productData.tnved.length >= 10) {
                    card.good_attrs.push({
                        attr_id: this.ATTR_IDS.TNVED_DETAILED,
                        attr_value: productData.tnved.substring(0, 10)
                    });
                }
            }
        }

        // Добавляем product_group_code если есть
        if (categoryInfo.productGroupCode) {
            card.product_group_code = categoryInfo.productGroupCode;
        }

        // Добавляем бренд
        if (productData.brand) {
            card.brand = productData.brand;
            card.good_attrs.push({
                attr_id: this.ATTR_IDS.BRAND,
                attr_value: productData.brand
            });
        }

        // Обязательные атрибуты
        
        // Страна производства
        card.good_attrs.push({
            attr_id: this.ATTR_IDS.COUNTRY,
            attr_value: productData.country || 'RU'
        });

        // Наименование
        card.good_attrs.push({
            attr_id: this.ATTR_IDS.PRODUCT_NAME,
            attr_value: fullName
        });

        // Опциональные атрибуты
        
        if (productData.color) {
            card.good_attrs.push({
                attr_id: this.ATTR_IDS.COLOR,
                attr_value: productData.color.toUpperCase()
            });
        }

        if (productData.size) {
            card.good_attrs.push({
                attr_id: this.ATTR_IDS.SIZE,
                attr_value: productData.size,
                attr_value_type: 'МЕЖДУНАРОДНЫЙ'
            });
        }

        if (productData.composition) {
            card.good_attrs.push({
                attr_id: this.ATTR_IDS.COMPOSITION,
                attr_value: productData.composition
            });
        }

        if (productData.article) {
            card.good_attrs.push({
                attr_id: this.ATTR_IDS.ARTICLE,
                attr_value: productData.article,
                attr_value_type: 'Артикул'
            });
        }

        if (productData.gender) {
            const genderMap = {
                'мужской': 'МУЖСКОЙ',
                'женский': 'ЖЕНСКИЙ',
                'унисекс': 'УНИСЕКС',
                'детский': 'ДЕТСКИЙ'
            };
            
            const normalizedGender = genderMap[productData.gender.toLowerCase()] || 'УНИСЕКС';
            
            card.good_attrs.push({
                attr_id: this.ATTR_IDS.GENDER,
                attr_value: normalizedGender
            });
        }

        return card;
    },

    /**
     * Получить боевой GTIN
     */
    async getLiveGTIN(nkToken, quantity = 1) {
        const response = await fetch(
            'https://markirovka.crpt.ru/api/v3/true-api/generate-gtins?quantity=' + quantity,
            {
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + nkToken
                }
            }
        );

        if (!response.ok) {
            throw new Error('Ошибка получения GTIN');
        }

        const data = await response.json();
        
        if (!data.drafts || data.drafts.length === 0) {
            throw new Error('GTIN не получен');
        }

        return data.drafts[0].gtin;
    },

    /**
     * Отправить карточку в НК
     */
    async sendCardToNK(cardData, nkToken) {
        const response = await fetch('https://markirovka.crpt.ru/api/v3/feed', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + nkToken
            },
            body: JSON.stringify(cardData)
        });

        if (!response.ok) {
            throw new Error('Ошибка отправки карточки');
        }

        const data = await response.json();
        const feedId = data.result?.feed_id;

        if (!feedId) {
            throw new Error('feed_id не получен');
        }

        return feedId;
    },

    /**
     * Опросить статус обработки карточки
     */
    async waitForFeedStatus(feedId, nkToken, maxRetries = 30, interval = 2000) {
        for (let i = 0; i < maxRetries; i++) {
            await new Promise(resolve => setTimeout(resolve, interval));

            const response = await fetch(
                'https://markirovka.crpt.ru/api/v3/feed-status?feed_id=' + feedId,
                {
                    headers: {
                        'Authorization': 'Bearer ' + nkToken
                    }
                }
            );

            if (!response.ok) {
                continue;
            }

            const status = await response.json();

            if (status.status === 'Processed') {
                return {
                    success: true,
                    gtin: status.item?.gtin,
                    status: status.status
                };
            } else if (status.status === 'Rejected') {
                return {
                    success: false,
                    status: status.status,
                    errors: status.errors || [],
                    validation_errors: status.validation_errors || []
                };
            }
            // Если Processing или InProgress - продолжаем опрос
        }

        return {
            success: false,
            status: 'Timeout',
            error: 'Превышено время ожидания'
        };
    },

    /**
     * Полный процесс создания карточки
     */
    async createCard(product, msToken, nkToken, options = {}) {
        console.log('Step 1: Извлечение данных товара...');
        const productData = await this.extractProductData(product, msToken);
        
        if (!productData.tnved) {
            throw new Error('У товара отсутствует ТН ВЭД код');
        }

        console.log('Step 2: Определение категории по ТН ВЭД...');
        const categoryInfo = await this.detectCategory(productData.tnved, nkToken);
        
        console.log('Step 3: Формирование данных карточки...');
        let cardData = this.createCardData(productData, categoryInfo, options);

        // Если нужен боевой GTIN
        if (!options.isTechGtin) {
            console.log('Step 4: Получение боевого GTIN...');
            const gtin = await this.getLiveGTIN(nkToken);
            cardData.gtin = gtin;
        }

        console.log('Step 5: Отправка карточки в НК...');
        const feedId = await this.sendCardToNK(cardData, nkToken);

        console.log('Step 6: Ожидание обработки...');
        const result = await this.waitForFeedStatus(feedId, nkToken);

        return {
            ...result,
            feedId: feedId,
            productData: productData,
            categoryInfo: categoryInfo
        };
    }
};

// Export для использования в разных средах
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NKLogic;
}