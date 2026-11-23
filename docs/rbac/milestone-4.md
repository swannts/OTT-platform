# Milestone 4: Customer access and playback entitlements

Customer playback now requires an authenticated Laravel request, a profile owned by that account, and the same profile being active on the current device. Laravel checks that the title is published, rejects restricted titles for child profiles, and verifies either free access, a paid PPV record with a successful transaction and unexpired window, or an active subscription whose plan level meets the title's required level.

Catalog detail responses no longer carry playback qualities or download URLs. The Next.js watch route requests playback details separately; the Laravel endpoint issues stream URLs only after authorization. The previous endpoint that decrypted any client-supplied Laravel-encrypted URL is no longer registered.

Profile switching and account APIs use the authenticated account as the owner boundary. Profile PINs and OTPs are no longer serialized in API resources. The web client records the selected profile ID locally for the playback request, while Laravel remains authoritative by checking the device's active profile.

Validation: `PlaybackEntitlementTest` covers free access, owned profile/device selection, child restrictions, paid subscription denial and grant, and removal of the arbitrary decrypt route. Web build and focused lint are recorded in the milestone 5 review because the shared web authentication proxy was added there.

Limitation: this authorization controls delivery through the Laravel API. A provider's underlying media URL may remain reusable after an entitled viewer receives it. Production-grade revocation and anti-redistribution require private storage or provider-issued expiring playback URLs/DRM, which cannot be inferred from the current generic URL fields.
