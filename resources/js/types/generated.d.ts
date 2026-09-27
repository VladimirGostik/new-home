declare namespace App {
namespace Data {
export type ActivityLogDetailData = {
readonly id: number,
readonly log_name: string | null,
readonly description: string,
readonly subject_type: string | null,
readonly subject_id: string | null,
readonly event: string | null,
readonly causer_name: string | null,
readonly causer_email: string | null,
readonly properties: Record<string, any> | null,
readonly attribute_changes: Record<string, any> | null,
readonly created_at: string,
};
export type ActivityLogIndexFilterData = {
readonly search: string | null,
readonly subject_type: string | null,
readonly user_filter: string | null,
readonly date_from: string | null,
readonly date_to: string | null,
readonly sort: string | null,
readonly perPage: number | null,
};
export type ActivityLogListItemData = {
readonly id: number,
readonly log_name: string | null,
readonly description: string,
readonly subject_type: string | null,
readonly subject_id: string | null,
readonly event: string | null,
readonly causer_name: string | null,
readonly causer_email: string | null,
readonly created_at: string,
};
export type AuthTokenData = {
readonly token: string,
readonly user: App.Data.UserListItemData,
};
export type ChangePasswordData = {
readonly current_password: string,
readonly password: string,
readonly password_confirmation: string,
};
export type CreateItemData = {
readonly name: string,
readonly note: string | null,
readonly room_id: string | null,
readonly unit_price: number | null,
readonly quantity: number,
readonly url: string | null,
readonly assigned_user_id: string | null,
readonly status: App.Enums.ItemStatus,
readonly priority: App.Enums.ItemPriority,
readonly photo_uuid: string | null,
readonly photo_url: string | null,
};
export type CreateItemVariantData = {
readonly name: string,
readonly unit_price: number | null,
readonly url: string | null,
readonly photo_uuid: string | null,
readonly photo_url: string | null,
};
export type CreateRoleData = {
readonly name: string,
readonly permissions: string[],
};
export type CreateRoomData = {
readonly name: string,
readonly description: string | null,
readonly sort_order: number | null,
};
export type CreateUserData = {
readonly name: string,
readonly email: string,
readonly password: string,
readonly password_confirmation: string,
readonly is_active: boolean,
readonly roles: string[],
};
export type DashboardData = {
readonly totals: App.Data.SpendSummaryData,
readonly unassigned_count: number,
readonly rooms: App.Data.SpendGroupData[],
readonly people: App.Data.SpendGroupData[],
};
export type ItemDetailData = {
readonly item: App.Data.ItemListItemData,
readonly variants: App.Data.ItemVariantListItemData[],
readonly comparison: App.Data.ItemVariantComparisonData | null,
};
export type ItemIndexFilterData = {
readonly search: string | null,
readonly room: string | null,
readonly assignee: string | null,
readonly status: string | null,
readonly priority: string | null,
readonly per_page: number | null,
};
export type ItemListItemData = {
readonly id: string,
readonly name: string,
readonly note: string | null,
readonly room_id: string | null,
readonly room_name: string | null,
readonly unit_price: number | null,
readonly quantity: number,
readonly total_price: number | null,
readonly url: string | null,
readonly assigned_user_id: string | null,
readonly assigned_user_name: string | null,
readonly status: string,
readonly priority: string,
readonly photo_uuid: string | null,
readonly photo_url: string | null,
readonly photo_thumb_url: string | null,
readonly created_at: string,
readonly selected_variant_id: string | null,
readonly selected_variant_name: string | null,
readonly variants_count: number,
};
export type ItemVariantComparisonData = {
readonly text: string,
readonly generated_at: string,
readonly is_stale: boolean,
};
export type ItemVariantListItemData = {
readonly id: string,
readonly name: string,
readonly unit_price: number | null,
readonly url: string | null,
readonly photo_uuid: string | null,
readonly photo_url: string | null,
readonly photo_thumb_url: string | null,
readonly vote_count: number,
readonly voter_names: string[],
readonly is_my_vote: boolean,
readonly is_selected: boolean,
};
export type LanguageSwitchData = {
readonly locale: string,
};
export type LoginData = {
readonly email: string,
readonly password: string,
readonly remember: boolean,
};
export type MediaDetailData = {
readonly id: number,
readonly uuid: string | null,
readonly file_name: string,
readonly name: string,
readonly size: number,
readonly mime_type: string | null,
readonly collection_name: string,
readonly disk: string,
readonly custom_properties: Record<string, any>,
readonly model_type: string,
readonly model_type_label: string,
readonly model_id: string | null,
readonly model_url: string | null,
readonly url: string,
readonly created_at: string,
};
export type MediaIndexFilterData = {
readonly search: string | null,
readonly model_type: string | null,
readonly collection_name: string | null,
readonly mime_type: string | null,
readonly sort: string | null,
readonly per_page: number | null,
};
export type MediaListItemData = {
readonly id: number,
readonly uuid: string | null,
readonly file_name: string,
readonly name: string,
readonly mime_type: string | null,
readonly size: number,
readonly collection_name: string,
readonly disk: string,
readonly model_type_label: string,
readonly model_id: string | null,
readonly model_url: string | null,
readonly url: string,
readonly created_at: string,
};
export type NavigationItemData = {
readonly key: string,
readonly label: string,
readonly href: string,
readonly icon: string,
readonly order: number,
readonly children: App.Data.NavigationItemData[],
};
export type NewPasswordData = {
readonly token: string,
readonly email: string,
readonly password: string,
readonly password_confirmation: string,
};
export type PasswordResetLinkData = {
readonly email: string,
};
export type ReorderRoomsData = {
readonly ids: string[],
};
export type RoleDetailData = {
readonly id: string,
readonly name: string,
readonly permissions: string[],
readonly users_count: number,
readonly is_system: boolean,
};
export type RoleIndexFilterData = {
readonly search: string | null,
readonly per_page: number | null,
};
export type RoleListItemData = {
readonly id: string,
readonly name: string,
readonly permissions_count: number,
readonly users_count: number,
readonly is_system: boolean,
};
export type RoomIndexFilterData = {
readonly search: string | null,
readonly per_page: number | null,
};
export type RoomListItemData = {
readonly id: string,
readonly name: string,
readonly description: string | null,
readonly sort_order: number,
readonly created_at: string,
readonly summary: App.Data.SpendSummaryData,
};
export type RoomOptionData = {
readonly id: string,
readonly name: string,
};
export type SaveItemVariantComparisonData = {
readonly text: string,
};
export type SpendGroupData = {
readonly id: string | null,
readonly name: string,
readonly summary: App.Data.SpendSummaryData,
};
export type SpendSummaryData = {
readonly items_count: number,
readonly bought_count: number,
readonly unpriced_count: number,
readonly total: number,
readonly bought_total: number,
readonly remaining_total: number,
};
export type StoreTemporaryUploadData = {
readonly file: File,
};
export type UpdateItemData = {
readonly name: string,
readonly note: string | null,
readonly room_id: string | null,
readonly unit_price: number | null,
readonly quantity: number,
readonly url: string | null,
readonly assigned_user_id: string | null,
readonly status: App.Enums.ItemStatus,
readonly priority: App.Enums.ItemPriority,
readonly photo_uuid: string | null,
readonly remove_photo: boolean,
};
export type UpdateItemVariantData = {
readonly name: string,
readonly unit_price: number | null,
readonly url: string | null,
readonly photo_uuid: string | null,
readonly remove_photo: boolean,
};
export type UpdateProfileData = {
readonly name: string,
readonly email: string,
readonly locale: string,
};
export type UpdateRoleData = {
readonly name: string,
readonly permissions: string[],
};
export type UpdateRoomData = {
readonly name: string,
readonly description: string | null,
readonly sort_order: number | null,
};
export type UpdateUserData = {
readonly name: string,
readonly email: string,
readonly is_active: boolean,
readonly roles: string[],
};
export type UserAutocompleteItemData = {
readonly id: string,
readonly name: string,
readonly email: string,
};
export type UserIndexFilterData = {
readonly search: string | null,
readonly per_page: number | null,
};
export type UserListItemData = {
readonly id: string,
readonly name: string,
readonly email: string,
readonly is_active: boolean,
readonly locale: string,
readonly roles: string[],
readonly created_at: string,
};
}
namespace Enums {
export type ItemPriority = 'low' | 'medium' | 'high';
export type ItemStatus = 'planned' | 'bought';
export type SupportedLanguage = 'sk' | 'en';
}
}
