<?php
namespace Drupal\Tests\flag\Kernel;

use Drupal\flag\Entity\Flag;
use Drupal\Tests\flag\Kernel\FlagKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the Flag counts API.
 *
 * @group flag
 */
class FlagCountsTest extends FlagKernelTestBase {

  /**
   * The flag.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * The node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * The flag count service.
   *
   * @var \Drupal\flag\FlagCountManagerInterface
   */
  protected $flagCountService;

  /**
   * User object.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * Anonymous user object.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $anonymousUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('user', 'users_data');

    // Create the anonymous role.
    $this->installConfig(['user']);

    $this->flagCountService = \Drupal::service('flag.count');

    // Create a flag. ( Non global ).
    $this->flag = Flag::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'global' => FALSE,
      'entity_type' => 'node',
      'bundles' => ['article'],
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
    ]);
    $this->flag->save();

    // Create admin user who may flag everything.
    $this->adminUser = $this->createUser([
      'administer flags',
    ]);

    // Grant the anonymous role permission to flag.
    /* @var \Drupal\user\RoleInterface $anonymous_role */
    $anonymous_role = Role::load(Role::ANONYMOUS_ID);
    $anonymous_role->grantPermission('flag ' . $this->flag->id());
    $anonymous_role->grantPermission('unflag ' . $this->flag->id());
    $anonymous_role->save();

    // Get the anonymous user.
    $this->anonymousUser = User::getAnonymousUser();

    $article = NodeType::create(['type' => 'article']);
    $article->save();

    // Create a node to flag.
    $this->node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(8),
    ]);
    $this->node->save();
  }

  /**
   * Tests that counts are kept in sync and can be retrieved.
   */
  public function testFlagCounts() {
    // Flag the node.
    $this->flagService->flag($this->flag, $this->node, $this->adminUser);

    // Check each of the count API functions.
    $flag_get_entity_flag_counts = $this->flagCountService->getFlagFlaggingCount($this->flag);
    $this->assertEqual($flag_get_entity_flag_counts, 1, "getFlagFlaggingCount() returns the expected count.");

    $flag_get_counts = $this->flagCountService->getEntityFlagCounts($this->node);
    $this->assertEqual($flag_get_counts[$this->flag->id()], 1, "getEntityFlagCounts() returns the expected count.");

    $flag_get_flag_counts = $this->flagCountService->getFlagEntityCount($this->flag);
    $this->assertEqual($flag_get_flag_counts, 1, "getFlagEntityCount() returns the expected count.");

    $this->flagService->unflagAllByFlag($this->flag);
    $flag_get_flag_counts = $this->flagCountService->getFlagEntityCount($this->flag);
    $this->assertEqual($flag_get_flag_counts, 0, "getFlagEntityCount() on reset flag returns the expected count.");
  }

  /**
   * Tests the differing counting rules between global and non-global flags.
   *
   * Global flags count all users as if they were are single user.
   * Non-global flags uniquely identify anonymous users by session_id.
   */
  public function testAnonymousFlagCount() {
    // Consider two distinct anonymous users.
    $anon1_session_id = 'Unknown user 1';
    $anon2_session_id = 'Unknown user 2';

    // Both users flag the node - using a non-global flag.
    $this->flagService->flag($this->flag, $this->node, $this->anonymousUser, $anon1_session_id);
    $this->flagService->flag($this->flag, $this->node, $this->anonymousUser, $anon2_session_id);

    // For non-global flags anonymous users can uniquely identifed by session_id.
    $anon1_count = $this->flagCountService->getUserFlagFlaggingCount($this->flag, $this->anonymousUser, $anon1_session_id);
    $this->assertEqual($anon1_count, 1, "getUserFlagFlaggingCount() counts only the first user.");
    $anon2_count = $this->flagCountService->getUserFlagFlaggingCount($this->flag, $this->anonymousUser, $anon2_session_id);
    $this->assertEqual($anon2_count, 1, "getUserFlagFlaggingCount() counts only the second user.");

    // Switch to a global flag, the accounting rules.
    $this->flag->setGlobal(TRUE);
    $this->flag->save();

    // Despite being a global flag, queries about specific anonymous users can still be made.
    $rejected_count = $this->flagCountService->getUserFlagFlaggingCount($this->flag, $this->anonymousUser, $anon1_session_id);
    $this->assertEqual($rejected_count, 1, "getUserFlagFlaggingCount() ignores the session id.");
  }

  /**
   * Tests flaggings are deleted and counts are removed when a flag is deleted.
   */
  public function testFlagDeletion() {
    // Create a article to flag.
    $article1 = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(8),
    ]);
    $article1->save();

    // Create a second article.
    $article2 = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(8),
    ]);
    $article2->save();

    // Flag both.
    $this->flagService->flag($this->flag, $article1, $this->adminUser);
    $this->flagService->flag($this->flag, $article2, $this->adminUser);

    // Confirm the counts have been incremented.
    $article1_count_before = $this->flagCountService->getEntityFlagCounts($article1);
    $this->assertEqual($article1_count_before[$this->flag->id()], 1, 'The article1 has been flagged.');
    $article2_count_before = $this->flagCountService->getEntityFlagCounts($article2);
    $this->assertEqual(count($article2_count_before[$this->flag->id()]), 1, 'The article2 has been flagged.');

    // Confirm the flagging have been created.
    $flaggings_before = $this->flagService->getFlagFlaggings($this->flag);
    $this->assertEqual(count($flaggings_before), 2, 'There are two flaggings associated with the flag');

    // Delete the flag.
    $this->flag->delete();

    // The list of all flaggings MUST now be empty.
    $flaggings_after = $this->flagService->getFlagFlaggings($this->flag);
    $this->assertEmpty($flaggings_after, 'The flaggings were removed, when the flag was deleted');

    // The flag id is now stale, so instead of searching for the flag in the
    // count array as before we require the entire array should be empty.
    $article1_counts_after = $this->flagCountService->getEntityFlagCounts($article1);
    $this->assertEmpty($article1_counts_after, 'Article1 counts has been removed.');
    $article2_counts_after = $this->flagCountService->getEntityFlagCounts($article2);
    $this->assertEmpty($article2_counts_after, 'Article2 counts has been removed.');
  }

  /**
   * Tests flaggings and counts are deleted when its entity is deleted.
   */
  public function testEntityDeletion() {
    // Create a article to flag.
    $article1 = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(8),
    ]);
    $article1->save();

    // Create a second article.
    $article2 = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(8),
    ]);
    $article2->save();

    // Flag both.
    $this->flagService->flag($this->flag, $article1, $this->adminUser);
    $this->flagService->flag($this->flag, $article2, $this->adminUser);

    // Confirm the counts have been incremented.
    $article1_count_before = $this->flagCountService->getEntityFlagCounts($article1);
    $this->assertEqual($article1_count_before[$this->flag->id()], 1, 'The article1 has been flagged.');
    $article2_count_before = $this->flagCountService->getEntityFlagCounts($article2);
    $this->assertEqual(count($article2_count_before[$this->flag->id()]), 1, 'The article2 has been flagged.');

    // Confirm the flagging have been created.
    $flaggings_before = $this->flagService->getFlagFlaggings($this->flag);
    $this->assertEqual(count($flaggings_before), 2, 'There are two flaggings associated with the flag');

    // Delete the entities.
    $article1->delete();
    $article2->delete();

    // The list of all flaggings MUST now be empty.
    $flaggings_after = $this->flagService->getFlagFlaggings($this->flag);
    $this->assert(empty($flaggings_after), 'The flaggings were removed, when the flag was deleted');

    // Confirm the counts have been removed.
    $article1_count_after = $this->flagCountService->getEntityFlagCounts($article1);
    $this->assertTrue(empty($article1_count_after), 'Article1 counts has been removed.');
    $article2_count_after = $this->flagCountService->getEntityFlagCounts($article2);
    $this->assertTrue(empty($article2_count_after), 'Article2 counts has been removed.');
  }

  /**
   * Tests flaggings and counts are deleted when its user is deleted.
   */
  public function testUserDeletion() {
    $auth_user = $this->createUser();

    // Create a flag.
    $user_flag = Flag::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'entity_type' => 'user',
      'flag_type' => 'entity:user',
      'link_type' => 'reload',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
    ]);
    $user_flag->save();

    $article = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(8),
    ]);
    $article->save();

    $this->flagService->flag($user_flag, $auth_user, $this->adminUser);
    $this->flagService->flag($this->flag, $article, $auth_user);

    $user_before_count = $this->flagCountService->getEntityFlagCounts($auth_user);
    $this->assertEqual($user_before_count[$user_flag->id()], 1, 'The user has been flagged.');

    $article_count_before = $this->flagCountService->getEntityFlagCounts($article);
    $this->assertEqual($article_count_before[$this->flag->id()], 1, 'The article has been flagged by the user.');

    $auth_user->delete();

    $flaggings_after = $this->flagService->getFlagFlaggings($user_flag);
    $this->assertEmpty($flaggings_after, 'The user flaggings were removed when the user was deleted.');

    $flaggings_after = $this->flagService->getFlagFlaggings($this->flag);
    $this->assert(empty($flaggings_after), 'The node flaggings were removed when the user was deleted');
  }

}
