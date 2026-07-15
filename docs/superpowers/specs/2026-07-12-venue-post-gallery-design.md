# Venue Post Gallery Design

## Goal

Let venue owners add up to 10 gallery images to a venue post while keeping the existing single thumbnail unchanged.

## User Flow

1. The owner selects up to 10 JPG, PNG, or WEBP images in the post form.
2. The form shows previews and lets the owner remove individual images.
3. On create, the API stores each accepted image as a `Media` record in the `gallery` collection.
4. On edit, the owner can keep existing gallery images, add new ones, or remove selected images.
5. The post detail page renders the gallery. List cards continue to use only the `thumbnail` collection.

## Technical Design

- Reuse `VenuePost::media()` and the existing `media` table; no migration is needed.
- Send new files in multipart field `gallery[]` and send IDs to remove in `removed_gallery_media_ids[]`.
- Validate at most 10 gallery images in total after considering retained images, 5 MB per image, and JPG/JPEG/PNG/WEBP types.
- Store gallery media under the existing public storage area with collection name `gallery`.
- Preserve thumbnail behavior and its `thumbnail` collection.

## Error Handling

- Reject files with an unsupported type or size over 5 MB.
- Reject submissions that would leave more than 10 gallery images.
- Do not allow an owner to delete media from another post.

## Verification

- Backend feature tests cover create, update, validation, and deletion of gallery media.
- Frontend source-level regression test checks multiple file selection and gallery form-data fields.
- Run the targeted tests and `npm run build`.
