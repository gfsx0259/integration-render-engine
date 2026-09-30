<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\StatusPull;

final class SaleStatusMapper
{
    private const array LOOKALIKES = [
        'а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h', 'о' => 'o', 'р' => 'p',
        'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x', 'і' => 'i',
        'А' => 'a', 'В' => 'b', 'Е' => 'e', 'К' => 'k', 'М' => 'm', 'Н' => 'h', 'О' => 'o', 'Р' => 'p',
        'С' => 'c', 'Т' => 't', 'У' => 'y', 'Х' => 'x', 'І' => 'i',
    ];

    private const array APPROVED_EXACT = [
        'approved',
        'deposit',
        'deposited',
        'depositor',
        'ftd',
    ];

    private const array APPROVED_NEEDLES = [
        'ftdsuccess', 'successfulftd', 'alreadyftd', 'hasdeposited', 'firstdeposit', 'depositedwithme',
        'selfdeposit',
    ];

    private const array APPROVED_BLOCKERS = [
        'potential', 'fail', 'declin', 'notdeposit', 'nodeposit', 'nondeposit',
    ];

    private const array DO_NOT_CALL_NEEDLES = [
        'dontcall', 'donotcall', 'donotcontact', 'dnc',
    ];

    private const array IN_PROGRESS_EXACT = [
        'new',
        'busy',
        'renew',
        'registered',
        'reregistered',
        'na', 'na1', 'na2', 'na3', 'na4', 'na5', 'ana', 'nahot',
        'vm', 'dvm', 'hu', 'hot',
    ];

    private const array IN_PROGRESS_NEEDLES = [
        'noanswer', 'pendingnoanswer', 'notanswer', 'stopanswering', 'callback', 'callagain',
        'callaagain', 'callgain', 'callinawhile', 'calllater', 'trylater', 'autocall', 'recall',
        'hungup', 'hangup', 'busy', 'canttalk', 'talking', 'unabletotalk', 'assign', 'reasing',
        'shuffle', 'followup', 'appointment', 'changeagent', 'failedattempt', 'firstattempt',
        'firstcontact', 'initialcontact', 'newcontact', 'notcontacted', 'tobecontacted', 'latestattempt',
        'insystem', 'systemprocessing', 'recovery', 'awaiting', 'abouttodeposit', 'depositsoon',
        'wire', 'initial', 'inicial', 'intial', 'firstcall', 'introcall', 'calling', 'dialing', 'oncall',
        'incall', 'inwork', 'inprocess', 'pending', 'priority', 'seguimiento', 'richiamare', 'arappeler',
        'meeting', 'voicemail', 'directvm', 'answeringmachine', 'huonintro', 'faileddeposit',
        'depositdecline', 'depdecline', 'paymentdecline', 'carddecline', 'ccdecline', 'failedpayment',
    ];

    private const array UNAVAILABLE_NEEDLES = [
        'wrongnumber', 'wrongphone', 'wronginfo', 'worngnumber', 'wnumber', 'badnumber',
        'brokennumber', 'falsenumber', 'incorrectnumber', 'invalidnumber', 'numbernot',
        'notinservice', 'disconnected', 'unreachable', 'notreachable', 'noline', 'badline',
        'blockedcall', 'callsblocked', 'wrongnr', 'wrongcontact', 'checknumber', 'callfailed',
    ];

    private const array INVALID_DATA_NEEDLES = [
        'invalid', 'ivalid', 'notvalid', 'nonvalid', 'validation', 'fake', 'trash', 'junk', 'spam', 'fraud',
        'test', 'duplicate', 'dublicate', 'doublelead', 'cross', 'regist', 'neverreg', 'didntreg',
        'didnotsign', 'neversign', 'deniedreg', 'irrelevant',
        'under18', 'underage', 'overage', 'ageissue', 'aboveage',
        'agerestriction', 'noage', 'ungerage', 'tooold', 'over79', 'over80', 'under30',
        'languagebarrier', 'wronglang', 'wronglenguage', 'nolanguage', 'anotherlanguage',
        'differentlanguage', 'otherlanguage', 'wrongage', 'wrongperson', 'wronggeo', 'country',
        'blacklist', 'immigrant', 'refugee', 'nodoc', 'wrongdata', 'wrongdetails', 'wrongemail',
        'incorrectdetails', 'badinfo', 'lowquality', 'potentialfraud', 'notqualified',
        'noqualified', 'disqualified', 'unqualified', 'notequalified', 'notqualifier',
        'noteligible', 'noneligible', 'ineligible',
    ];

    private const array NOT_INTERESTED_NEEDLES = [
        'notinterested', 'notinterest', 'nointerest', 'notintrested', 'nointrest', 'lowinterest',
        'lowpot', 'nopot', 'zeropotential', 'notpotential', 'potentiallow', 'potetnial', 'weakpotential',
        'decline', 'stoppedanswering', 'neveranswer', 'neveransswer', 'neweranswer', 'nomoney', 'lowmoney',
        'notenoughmoney', 'nofund', 'insufficientfunds', 'alreadydeposited', 'depositedwith',
        'depositedelsewhere', 'depositedelswhere', 'depositwithanother', 'depositwithother',
        'anothercompany', 'othercompany', 'anotherbrand', 'anotherbroker', 'alreadyinvest', 'alredayinvest',
        'abusive',
    ];

    private const array LATE_IN_PROGRESS_NEEDLES = [
        'potential',
    ];

    private const array LANGUAGE_NAMES = [
        'italian', 'german', 'french', 'spanish', 'english', 'portuguese', 'dutch', 'polish', 'arabic',
        'russian', 'turkish', 'czech', 'hungarian', 'romanian', 'greek', 'swedish', 'norwegian', 'danish',
        'finnish', 'hebrew', 'hindi',
    ];

    /**
     * @return array{status: string, reason: ?string}|null
     */
    public function map(?string $saleStatus): ?array
    {
        $class = $this->classify($this->normalize($saleStatus));

        return match ($class) {
            'approved' => ['status' => 'approved', 'reason' => null],
            'unavailable', 'invalid_data', 'not_interested' => ['status' => 'rejected', 'reason' => $class],
            default => null,
        };
    }

    public function isInProgress(?string $saleStatus): bool
    {
        return $this->classify($this->normalize($saleStatus)) === 'in_progress';
    }

    public function isUnknown(?string $saleStatus): bool
    {
        return $this->classify($this->normalize($saleStatus)) === null;
    }

    private function classify(string $key): ?string
    {
        return match (true) {
            $key === '' => 'in_progress',
            $this->isApproved($key) => 'approved',
            $this->containsAny($key, self::DO_NOT_CALL_NEEDLES) => 'not_interested',
            in_array($key, self::IN_PROGRESS_EXACT, true),
            $this->containsAny($key, self::IN_PROGRESS_NEEDLES) => 'in_progress',
            $this->containsAny($key, self::UNAVAILABLE_NEEDLES) => 'unavailable',
            $this->containsAny($key, self::INVALID_DATA_NEEDLES), $this->isLanguageMismatch($key) => 'invalid_data',
            $this->containsAny($key, self::NOT_INTERESTED_NEEDLES) => 'not_interested',
            str_starts_with($key, 'new'),
            $this->containsAny($key, self::LATE_IN_PROGRESS_NEEDLES) => 'in_progress',
            default => null,
        };
    }

    private function isApproved(string $key): bool
    {
        if (in_array($key, self::APPROVED_EXACT, true)) {
            return true;
        }

        if ($this->containsAny($key, self::APPROVED_BLOCKERS)) {
            return false;
        }

        return str_starts_with($key, 'ftd') || $this->containsAny($key, self::APPROVED_NEEDLES);
    }

    private function isLanguageMismatch(string $key): bool
    {
        foreach (self::LANGUAGE_NAMES as $language) {
            if (
                str_contains($key, 'no' . $language)
                || str_contains($key, 'not' . $language)
                || str_contains($key, 'na' . $language)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $key, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(?string $saleStatus): string
    {
        if ($saleStatus === null) {
            return '';
        }

        return preg_replace('/[^a-z0-9]+/', '', strtolower(strtr(trim($saleStatus), self::LOOKALIKES))) ?? '';
    }
}
