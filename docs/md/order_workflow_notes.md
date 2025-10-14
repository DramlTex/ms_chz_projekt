# Order Workflow Notes from API_SUZ_3_0_full_markdown_sections.md

## Findings
- The document confirms that the "Order" object exists and references associated structures like `OrderProduct`, but the detailed schema per product group is omitted and points back to the original manual for specifics.
- Business process 01.03.00.00 ("Получить КМ из заказа") is only described at a high level (validation, code emission, response handling) without concrete REST endpoint paths or payload schemas.
- Sections 4.4.21–4.4.24 describe document search, retrieval, and submission flows, implying that orders are handled as documents, yet the actual request body for creating an order is not provided in this markdown extract.
- Utilisation report examples (section 4.4.11) are present and include required headers, query parameters, and sample payloads for different product groups (e.g., tobacco, beer), but these apply after codes have been obtained and do not explain how to trigger emission.
- Authentication coverage (section 9) reiterates how to obtain a `clientToken` using `/auth/simpleSignIn/{omsConnection}`; no further steps for registering or using the token in order placement are documented here.

## Gaps Remaining
- No explicit HTTP method/URL or JSON schema for submitting an order request.
- No description of how to poll order status or download the resulting code arrays beyond brief mentions.
- Missing information about prerequisites such as template selection, serial number ranges, or payment details per product group.
- No walkthrough from order creation to code retrieval and utilisation report submission.

## Next Steps
- Request the sections of the original manual that contain the full definition of the `Order` payload and the REST endpoints for creating and processing orders.
- Obtain documentation covering order status polling (`/orders/status` or similar) and the API that returns code arrays (buffers).
- Acquire guidance on converting received buffers into GS1 DataMatrix codes, including checksum handling, if not already documented elsewhere.
