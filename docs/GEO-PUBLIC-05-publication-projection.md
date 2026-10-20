# GEO-PUBLIC-05 — Publication projection

## Authority

The versioned writer is private to the capability-checked Publication Service B3 path. Normal admin approval still uses the B2 transition then B3. Direct preparation/validation outside B3 remains fenced for versioned reports. Decision saves and intake cannot create a public projection. Metadata short-circuit guards prevent all non-writer additions, updates and deletions of versioned geometry and public coordinate mirrors; legacy editor behavior remains unchanged.

## Exact authorization

Requires current request/version, a valid exact decision under public-location/v1, exact_confirmed=true, valid matching canonical/table private coordinates, approved moderation, B3 capabilities, ordinary publication validation and a successful server safety review. The GEO-PUBLIC-04 operator confirmation supplies the initial positive review inside B3 only. The existing badaround_public_geo_exact_allowed hook can veto this review. A hook cannot override a missing exact confirmation or request. No category implicitly grants precise publication.

## Stored geometry and integrity

_ba_public_geo contains version, explicit mode, optional latitude/longitude and radius_meters. Exact uses radius_meters=0 solely for compatibility: zero has no exactness semantics. Consumers use explicit mode plus a valid signature. Coordinates are also persisted to legacy public mirror keys for minimal consumer compatibility. A keyed proof binds the event, full projection, decision and a one-way fingerprint of the private primary report and radius policy. No address is copied. Discovery and detail read only the sealed public projection and never read ba_reports. Invalid or inconsistent public geometry returns no marker.

Approximate centers use a keyed, stable spherical offset, generated only in B3 and persisted once. The versioned v1 radius policy preserves the existing f32-c4=150m, f32-c5=300m, f32-c6=1000m, f32-c7=3000m and documented default 750m. Street uses 300m. No arbitrary moderator radius field is accepted by the decision contract. Approximate/private equality within the existing 0.00001-degree tolerance is rejected.

## Commit and update safety

Versioned B3 locks the event and primary report and requires InnoDB. Geometry, controlled publication state and coordinate-free geographic audit commit atomically; errors roll back and clear post caches. The custom published action is deferred until commit. Repeated publication verifies and reuses the existing geometry without additional geographic audit or random sampling. An altered decision, private source, policy input, signature or mirror fails closed. Reprojection of already-published events is outside scope and is denied. Municipality and territory-only can remain coordinate-free, with no centroid fallback. All unversioned legacy paths retain their original semantics, including generalized point and #290 recovery.

## Release gate

Staging deployment does not publish any event. Reports 31/32 and events 303/304 remain pending until separate explicit authorization. Protected fixtures are read-only. No production, merge, schema, SEO, social dispatch or map design changes. Final exact/approximate real publication QA and certification remain pending at the pre-publication gate.
