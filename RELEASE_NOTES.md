# FOX Currency Rate API v1.1.0

## Highlights
- New REST endpoint `GET /wp-json/fox-rate/v1/rate` with a 5-minute local cache.
- 2-second upstream timeout with automatic recovery to the last known good value (up to 24 h old).
- Responses now include `Cache-Control: no-store` and `Pragma: no-cache` headers.

## Endpoint
`GET /wp-json/fox-rate/v1/rate?from=USD&to=VES`

### Parameters
- `from` (optional, string, default `USD`): ISO currency code of the source currency.
- `to` (optional, string, default `VES`): ISO currency code of the target currency.

### Response
```json
{
  "rate": 36.42,
  "from": "USD",
  "to": "VES",
  "updated": 1693526400,
  "ttl": 300,
  "source": "upstream",
  "stale": false
}
```

Fields:
- `rate`: Floating-point rate value.
- `from`: Source currency code.
- `to`: Target currency code.
- `updated`: Unix timestamp for the last refresh time.
- `ttl`: Cache time-to-live in seconds (300).
- `source`: `upstream`, `cache`, or `stale` depending on the response origin.
- `stale` (boolean, optional): Present and set to `true` when the response is served from the stale fallback. The response can also include `age` with the number of seconds since the data was refreshed.
