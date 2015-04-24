<?php
/**
 * @file
 * Contains \Drupal\flag\FlagServiceInterface.
 */

namespace Drupal\flag;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagInterface;

/**
 * Flag service interface.
 */
interface FlagServiceInterface {

  /**
   * Get a flag type definition.
   *
   * @param string $entity_type
   *   (optional) The entity type to get the definition for, or NULL to return
   *   all flag subtypes.
   *
   * @return array
   *   The flag type definition array.
   *
   * @see hook_flag_type_info()
   */
  public function fetchDefinition($entity_type = NULL);

  /**
   * List all flags available.
   *
   * If node type or account are entered, a list of all possible flags will be
   * returned.
   *
   * @param string $entity_type
   *   (optional) The type of entity for which to load the flags.
   * @param string $bundle
   *   (optional) The bundle for which to load the flags.
   * @param AccountInterface $account
   *   (optional) The user account to filter available flags. If not set, all
   *   flags for the given entity and bundle will be returned.
   *
   * @return array
   *   An array of the structure [fid] = flag_object.
   */
  public function getFlags($entity_type = NULL, $bundle = NULL, AccountInterface $account = NULL);

  /**
   * Get a flagging that already exists.
   *
   * @param FlagInterface $flag
   *   The flag.
   * @param EntityInterface $entity
   *   The flaggable entity.
   * @param AccountInterface $account
   *   (optional) The account of the flagging user. If omitted, the flagging for
   *   the current user will be returned.
   *
   * @return FlaggingInterface|null
   *   The flagging or NULL if the flagging is not found.
   *
   */
  public function getFlagging(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL);

  /**
   * Get all flaggings for the given entity, flag, and optionally, user.
   *
   * @param FlagInterface $flag
   *   (optional) The flag entity. If NULL, flaggings for any flag will be
   *   returned.
   * @param EntityInterface $entity
   *   (optional) The flaggable entity. If NULL, flaggings for any entity will be
   *   returned.
   * @param AccountInterface $account
   *   (optional) The account of the flagging user. If NULL, flaggings for any
   *   user will be returned.
   *
   * @return array
   *   An array of flaggings.
   */
   public function getFlaggings(FlagInterface $flag = NULL, EntityInterface $entity = NULL, AccountInterface $account = NULL);

  /**
   * Load the flag entity given the ID.
   *
   * @param int $flag_id
   *   The ID of the flag to load.
   *
   * @return FlagInterface|null
   *   The flag entity.
   */
  public function getFlagById($flag_id);

  /**
   * Loads the flaggable entity given the flag entity and entity ID.
   *
   * @param FlagInterface $flag
   *   The flag entity.
   * @param int $entity_id
   *   The ID of the flaggable entity.
   *
   * @return EntityInterface|null
   *   The flaggable entity object.
   */
  public function getFlaggableById(FlagInterface $flag, $entity_id);

  /**
   * Get a list of users that have flagged an entity.
   *
   * @param EntityInterface $entity
   *   The entity object.
   * @param FlagInterface $flag
   *   (optional) The flag entity to which to restrict results.
   *
   * @return array
   *   An array of users who have flagged the entity.
   */
  public function getFlaggingUsers(EntityInterface $entity, FlagInterface $flag = NULL);

  /**
   * Flags the given entity given the flag and entity objects.
   *
   * @param FlagInterface $flag
   *   The flag entity.
   * @param EntityInterface $entity
   *   The entity to flag.
   * @param AccountInterface $account
   *   (optional) The account of the user flagging the entity. If not given,
   *   the current user is used.
   *
   * @return FlaggingInterface|null
   *   The flagging.
   */
  public function flag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL);

  /**
   * Unflags the given entity for the given flag.
   *
   * @param FlagInterface $flag
   *   The flag being unflagged.
   * @param EntityInterface $entity
   *   The entity to unflag.
   * @param AccountInterface $account
   *   (optional) The account of the user that created the flagging.
   *
   * @return array
   *   An array of flagging IDs to delete.
   */
  public function unflag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL);

  /**
   * Deletes the given flagging.
   *
   * @param FlaggingInterface $flagging
   *   The flagging to delete.
   */
  public function unflagByFlagging(FlaggingInterface $flagging);

}
