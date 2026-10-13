# Network test fixtures

`GeoLite2-Country-Test.mmdb` and `GeoLite2-ASN-Test.mmdb` are MaxMind's public test databases from
<https://github.com/maxmind/MaxMind-DB> (`test-data/`). MaxMind, Inc. licenses that repository under Apache-2.0 or MIT, at the user's option; they are
redistributed here under the MIT License, whose text and copyright notice is `LICENSE-MIT` in this directory. They are tiny, synthetic and not real geolocation data. The matching published source
data is in the same repo under `source-data/`; the test IPs are taken from it:

| IP             | Country | ASN   | Organisation |
|----------------|---------|-------|--------------|
| 89.160.20.128  | SE      | 29518 | Bredband2 AB |
| 216.160.83.56  | US      | 209   | (none)       |
| 67.43.156.1    | BT      | 35908 | (none)       |
| 2a02:d180::1   | DE      | (none)| (none)       |

DB-IP Lite is MMDB with `compat=GeoLite2-ASN` / GeoLite2-Country field names, so the same reader code path runs.
